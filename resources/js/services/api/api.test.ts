import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '@/services';
import { departmentsApi } from '@/services/api/departments';
import { familiesApi } from '@/services/api/families';
import { membersApi } from '@/services/api/members';
import { family, member, memberSummary, pageOf } from '@/test/fixtures';

vi.mock('@/services', () => ({ api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn() } }));

const respond = (data: unknown) => Promise.resolve({ data });

describe('membersApi', () => {
    beforeEach(() => vi.clearAllMocks());

    it('asks for a page of members and leaves empty filters out of the request', async () => {
        vi.mocked(api.get).mockReturnValue(respond(pageOf([memberSummary()])));

        const result = await membersApi.list({ search: 'abena', status: '', gender: 'female', department_id: '', page: 2 });

        expect(api.get).toHaveBeenCalledWith('/members', { params: { page: 2, search: 'abena', gender: 'female' } });
        expect(result.data).toHaveLength(1);
    });

    it('unwraps a single member from the data envelope', async () => {
        vi.mocked(api.get).mockReturnValue(respond({ data: member() }));

        expect((await membersApi.get('member-1')).first_name).toBe('Emmanuel');
        expect(api.get).toHaveBeenCalledWith('/members/member-1');
    });

    it('creates with POST and updates with PUT', async () => {
        vi.mocked(api.post).mockReturnValue(respond({ data: member() }));
        vi.mocked(api.put).mockReturnValue(respond({ data: member() }));

        await membersApi.create({ first_name: 'Abena', last_name: 'Asante', gender: 'female', phone: '0200987654' });
        await membersApi.update('member-1', { occupation: 'Nurse' });

        expect(api.post).toHaveBeenCalledWith('/members', expect.objectContaining({ first_name: 'Abena' }));
        expect(api.put).toHaveBeenCalledWith('/members/member-1', { occupation: 'Nurse' });
    });

    it('changes status with PATCH and sends the note', async () => {
        vi.mocked(api.patch).mockReturnValue(respond({ data: member() }));

        await membersApi.changeStatus('member-1', 'inactive', 'Relocated');

        expect(api.patch).toHaveBeenCalledWith('/members/member-1/status', { status: 'inactive', note: 'Relocated' });
    });

    it('uploads the photo as a file and returns the new signed link', async () => {
        vi.mocked(api.post).mockReturnValue(respond({ photo_url: 'https://signed.example/p.jpg' }));
        const file = new File(['x'], 'me.jpg', { type: 'image/jpeg' });

        const url = await membersApi.uploadPhoto('member-1', file);

        expect(url).toBe('https://signed.example/p.jpg');
        const [path, body] = vi.mocked(api.post).mock.calls[0] ?? [];
        expect(path).toBe('/members/member-1/photo');
        expect(body).toBeInstanceOf(FormData);
        expect((body as FormData).get('photo')).toBe(file);
    });

    it('adds a milestone to the member', async () => {
        vi.mocked(api.post).mockReturnValue(respond({ data: { id: 'ms1' } }));

        await membersApi.addMilestone('member-1', { type: 'baptism', occurred_at: '2024-06-02' });

        expect(api.post).toHaveBeenCalledWith('/members/member-1/milestones', { type: 'baptism', occurred_at: '2024-06-02' });
    });
});

describe('familiesApi', () => {
    beforeEach(() => vi.clearAllMocks());

    it('asks for a page of families, with the search only when there is one', async () => {
        vi.mocked(api.get).mockReturnValue(respond(pageOf([family()])));

        await familiesApi.list({ search: '', page: 1 });
        await familiesApi.list({ search: 'mensah', page: 3 });

        expect(api.get).toHaveBeenNthCalledWith(1, '/families', { params: { page: 1 } });
        expect(api.get).toHaveBeenNthCalledWith(2, '/families', { params: { page: 3, search: 'mensah' } });
    });

    it('reads every page when a picker needs all the families', async () => {
        vi.mocked(api.get)
            .mockReturnValueOnce(respond(pageOf([family({ id: 'a' })], { current_page: 1, last_page: 3 })))
            .mockReturnValueOnce(respond(pageOf([family({ id: 'b' })], { current_page: 2, last_page: 3 })))
            .mockReturnValueOnce(respond(pageOf([family({ id: 'c' })], { current_page: 3, last_page: 3 })));

        const all = await familiesApi.listAll();

        expect(all.map((f) => f.id)).toEqual(['a', 'b', 'c']);
        expect(api.get).toHaveBeenCalledTimes(3);
        expect(api.get).toHaveBeenNthCalledWith(2, '/families', { params: { page: 2, per_page: 100 } });
    });

    it('makes one request when everything fits on one page', async () => {
        vi.mocked(api.get).mockReturnValue(respond(pageOf([family()])));

        await familiesApi.listAll();

        expect(api.get).toHaveBeenCalledTimes(1);
    });

    it('creates and renames', async () => {
        vi.mocked(api.post).mockReturnValue(respond({ data: family() }));
        vi.mocked(api.put).mockReturnValue(respond({ data: family() }));

        await familiesApi.create({ name: 'Asante Family' });
        await familiesApi.update('family-1', { name: 'New' });

        expect(api.post).toHaveBeenCalledWith('/families', { name: 'Asante Family' });
        expect(api.put).toHaveBeenCalledWith('/families/family-1', { name: 'New' });
    });
});

describe('departmentsApi', () => {
    it('asks only for active departments, as many as one request allows', async () => {
        vi.mocked(api.get).mockReturnValue(respond(pageOf([])));

        await departmentsApi.list();

        expect(api.get).toHaveBeenCalledWith('/departments', { params: { per_page: 100, is_active: 1 } });
    });
});

describe('membersApi privacy calls', () => {
    beforeEach(() => vi.clearAllMocks());

    it('reads the data export without unwrapping it', async () => {
        vi.mocked(api.get).mockReturnValue(respond({ exported_at: 'now', member: {} }));

        expect(await membersApi.exportData('member-1')).toEqual({ exported_at: 'now', member: {} });
        expect(api.get).toHaveBeenCalledWith('/members/member-1/data-export');
    });

    it('erases with a reason', async () => {
        vi.mocked(api.post).mockReturnValue(respond(undefined));

        await membersApi.erase('member-1', 'Member request');

        expect(api.post).toHaveBeenCalledWith('/members/member-1/erasure', { reason: 'Member request' });
    });
});
