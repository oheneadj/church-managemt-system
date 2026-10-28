import { useState } from 'react';
import { Link } from 'react-router';
import { AddMilestoneDialog } from '@/components/Members/AddMilestoneDialog';
import { MilestoneList } from '@/components/Members/MilestoneList';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DetailList } from '@/components/ui/DetailList';
import { ROUTES } from '@/constants/routes';
import type { Member } from '@/types/members';
import { formatDate } from '@/utils/formatDate';

interface MemberProfileTabProps {
    member: Member;
    /** Whether the signed-in user may add milestones to this member. */
    editable: boolean;
}

/** Yes or no, in words, for the messaging preferences. */
function yesNo(value: boolean): string {
    return value ? 'Yes' : 'No';
}

/**
 * Everything the church knows about the member, grouped into Contact, Personal, Church life and Milestones.
 * Milestones appear only for roles the API sends them to.
 */
export function MemberProfileTab({ member, editable }: MemberProfileTabProps) {
    const [addingMilestone, setAddingMilestone] = useState(false);

    return (
        <div className="flex flex-col gap-4">
            <Card>
                <CardHeader>
                    <CardTitle>Contact</CardTitle>
                </CardHeader>
                <CardContent>
                    <DetailList
                        items={[
                            { label: 'Phone', value: member.phone },
                            { label: 'Second phone', value: member.phone_secondary },
                            { label: 'Email', value: member.email },
                            { label: 'Address', value: member.address },
                            { label: 'Nearest landmark', value: member.nearest_landmark },
                            { label: 'Digital address', value: member.digital_address },
                            { label: 'Text messages', value: yesNo(member.sms_opted_in) },
                            { label: 'WhatsApp messages', value: yesNo(member.whatsapp_opted_in) },
                        ]}
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Personal</CardTitle>
                </CardHeader>
                <CardContent>
                    <DetailList
                        items={[
                            { label: 'Gender', value: member.gender_label },
                            { label: 'Date of birth', value: member.date_of_birth ? formatDate(member.date_of_birth) : null },
                            { label: 'Marital status', value: member.marital_status_label },
                            { label: 'Education', value: member.educational_level_label },
                            { label: 'Occupation', value: member.occupation },
                            { label: 'Tribe', value: member.tribe },
                            { label: 'Language', value: member.language_preference },
                        ]}
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Church life</CardTitle>
                </CardHeader>
                <CardContent>
                    <DetailList
                        items={[
                            { label: 'Membership type', value: member.membership_type_label },
                            { label: 'Member since', value: member.membership_date ? formatDate(member.membership_date) : null },
                            { label: 'Assigned pastor', value: member.assigned_pastor?.name ?? null },
                            { label: 'Departments', value: member.departments?.map((department) => department.name).join(', ') ?? null },
                            {
                                label: 'Family',
                                value: member.family ? (
                                    <Link to={ROUTES.FAMILY(member.family.id)} className="underline underline-offset-4">
                                        {member.family.name}
                                        {member.family.role_label && ` (${member.family.role_label})`}
                                    </Link>
                                ) : null,
                            },
                        ]}
                    />
                </CardContent>
            </Card>

            {member.milestones !== undefined && (
                <Card>
                    <CardHeader>
                        <CardTitle>Milestones</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        <MilestoneList milestones={member.milestones} />
                        {editable && (
                            <div>
                                <Button type="button" variant="outline" onClick={() => setAddingMilestone(true)}>
                                    Add milestone
                                </Button>
                                <AddMilestoneDialog memberId={member.id} open={addingMilestone} onOpenChange={setAddingMilestone} />
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
