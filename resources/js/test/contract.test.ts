import Ajv2020 from 'ajv/dist/2020';
import addFormats from 'ajv-formats';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { describe, expect, it } from 'vitest';
import {
    attendanceRecord,
    checkinService,
    family,
    qrCheckedIn,
    qrCode,
    member,
    memberSummary,
    pageOf,
    recorded,
    service,
    serviceAttendance,
    serviceSchedule,
    visitor,
} from '@/test/fixtures';

/**
 * Contract test (agreement section 35): the sample data this app's tests are built on must match what the API
 * promises in the committed spec, docs/openapi.json. If the spec changes a field's type, adds a required field or
 * drops one, the sample data stops matching and this fails, instead of the app finding out in production.
 * The other half, that the real API answers as the spec says, is tests/Feature/Contract/ApiContractTest.php.
 */
const spec = JSON.parse(readFileSync(path.resolve(import.meta.dirname, '../../../docs/openapi.json'), 'utf8')) as Record<string, unknown>;

/** Checks a value against the schema the spec gives for an operation's response, and returns the problems found. */
function problemsWith(value: unknown, method: string, apiPath: string, status: number): string[] {
    const ajv = new Ajv2020({ strict: false, allErrors: true });
    addFormats(ajv);

    const pointer = `#/paths/${apiPath.replaceAll('/', '~1')}/${method}/responses/${status}/content/application~1json/schema`;
    const validate = ajv.compile({ ...spec, $ref: pointer });

    return validate(value) ? [] : (validate.errors ?? []).map((error) => `${error.instancePath || '/'} ${error.message ?? ''}`);
}

describe('the sample data the app is tested with matches the API spec', () => {
    it('GET /members: a page of directory rows', () => {
        expect(
            problemsWith(
                pageOf([
                    memberSummary(),
                    memberSummary({ id: 'member-2', photo_url: 'https://files.example/photo.jpg', family_id: 'family-1' }),
                ]),
                'get',
                '/v1/members',
                200,
            ),
        ).toEqual([]);
    });

    it('GET /members/{member}: a full member record', () => {
        const record = member({
            family: { id: 'family-1', name: 'Mensah Family', role: 'head', role_label: 'Head' },
            departments: [{ id: 'dept-1', name: 'Choir', role: null }],
        });

        expect(problemsWith({ data: record }, 'get', '/v1/members/{member}', 200)).toEqual([]);
    });

    it('GET /families/{family}: a family', () => {
        expect(problemsWith({ data: family() }, 'get', '/v1/families/{family}', 200)).toEqual([]);
    });

    it('GET /services: a page of services', () => {
        expect(
            problemsWith(
                pageOf([
                    service(),
                    service({
                        id: 'service-2',
                        is_open: true,
                        starts_at: null,
                        ends_at: null,
                        checkin_code: null,
                        headcount_override: 140,
                    }),
                ]),
                'get',
                '/v1/services',
                200,
            ),
        ).toEqual([]);
    });

    it('GET /services/{service}: one service', () => {
        expect(problemsWith({ data: service() }, 'get', '/v1/services/{service}', 200)).toEqual([]);
    });

    it('GET /services/{service}/attendance: the headcount and a page of people', () => {
        const withVisitor = serviceAttendance([
            attendanceRecord(),
            attendanceRecord({ id: 'record-2', member: null, visitor_name: 'Yaw Boateng' }),
        ]);

        expect(problemsWith(withVisitor, 'get', '/v1/services/{service}/attendance', 200)).toEqual([]);
    });

    it('POST /services/{service}/attendance: what a bulk check-in did', () => {
        expect(
            problemsWith(
                recorded({ errors: [{ member_id: 'x', message: 'Member not found.' }] }),
                'post',
                '/v1/services/{service}/attendance',
                201,
            ),
        ).toEqual([]);
    });

    it('POST /services/{service}/visitors: a visitor', () => {
        expect(problemsWith({ data: visitor() }, 'post', '/v1/services/{service}/visitors', 201)).toEqual([]);
    });

    it('GET /services/{service}/qr: the code to draw', () => {
        expect(problemsWith(qrCode(), 'get', '/v1/services/{service}/qr', 200)).toEqual([]);
    });

    it('GET /checkin/qr/{token}: the service a scan is for', () => {
        expect(problemsWith(checkinService(), 'get', '/v1/checkin/qr/{token}', 200)).toEqual([]);
    });

    it('POST /checkin/qr: the check-in confirmation', () => {
        expect(problemsWith(qrCheckedIn(), 'post', '/v1/checkin/qr', 200)).toEqual([]);
    });

    it('GET /service-schedules: a page of weekly schedules', () => {
        expect(
            problemsWith(
                pageOf([serviceSchedule(), serviceSchedule({ id: 'schedule-2', starts_at: null, ends_at: null, is_active: false })]),
                'get',
                '/v1/service-schedules',
                200,
            ),
        ).toEqual([]);
    });

    it('POST /service-schedules and PUT /service-schedules/{id}: one schedule', () => {
        expect(problemsWith({ data: serviceSchedule() }, 'post', '/v1/service-schedules', 201)).toEqual([]);
        expect(
            problemsWith({ data: serviceSchedule({ name: 'Worship' }) }, 'put', '/v1/service-schedules/{service_schedule}', 200),
        ).toEqual([]);
    });

    it('notices a field that is not what the spec says', () => {
        const problems = problemsWith(pageOf([{ ...memberSummary(), member_number: 12 }]), 'get', '/v1/members', 200);

        expect(problems.join()).toContain('member_number');
    });

    it('notices a required field that is missing', () => {
        const withoutPhone: Partial<ReturnType<typeof memberSummary>> = memberSummary();
        delete withoutPhone.phone;

        expect(problemsWith(pageOf([withoutPhone]), 'get', '/v1/members', 200).join()).toContain('phone');
    });
});
