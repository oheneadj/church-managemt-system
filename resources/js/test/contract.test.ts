import Ajv2020 from 'ajv/dist/2020';
import addFormats from 'ajv-formats';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { describe, expect, it } from 'vitest';
import { family, member, memberSummary, pageOf } from '@/test/fixtures';

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
