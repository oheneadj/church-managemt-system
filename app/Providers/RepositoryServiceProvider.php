<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\AttendanceRecordRepositoryInterface;
use App\Repositories\AuditLogRepositoryInterface;
use App\Repositories\ChurchPurgeRepositoryInterface;
use App\Repositories\ChurchRepositoryInterface;
use App\Repositories\ConsentRepositoryInterface;
use App\Repositories\ContributionRepositoryInterface;
use App\Repositories\DepartmentRepositoryInterface;
use App\Repositories\EloquentAttendanceRecordRepository;
use App\Repositories\EloquentAuditLogRepository;
use App\Repositories\EloquentChurchPurgeRepository;
use App\Repositories\EloquentChurchRepository;
use App\Repositories\EloquentConsentRepository;
use App\Repositories\EloquentContributionRepository;
use App\Repositories\EloquentDepartmentRepository;
use App\Repositories\EloquentFamilyRepository;
use App\Repositories\EloquentFundTypeRepository;
use App\Repositories\EloquentHandledWhatsappMessageRepository;
use App\Repositories\EloquentMemberDepartmentRepository;
use App\Repositories\EloquentMemberMilestoneRepository;
use App\Repositories\EloquentMemberRepository;
use App\Repositories\EloquentMessageLogRepository;
use App\Repositories\EloquentPledgeRepository;
use App\Repositories\EloquentServiceRepository;
use App\Repositories\EloquentServiceScheduleRepository;
use App\Repositories\EloquentSmsCreditLedgerRepository;
use App\Repositories\EloquentUserRepository;
use App\Repositories\EloquentVisitorRecordRepository;
use App\Repositories\EloquentWebhookLogRepository;
use App\Repositories\FamilyRepositoryInterface;
use App\Repositories\FundTypeRepositoryInterface;
use App\Repositories\HandledWhatsappMessageRepositoryInterface;
use App\Repositories\MemberDepartmentRepositoryInterface;
use App\Repositories\MemberMilestoneRepositoryInterface;
use App\Repositories\MemberRepositoryInterface;
use App\Repositories\MessageLogRepositoryInterface;
use App\Repositories\PledgeRepositoryInterface;
use App\Repositories\ServiceRepositoryInterface;
use App\Repositories\ServiceScheduleRepositoryInterface;
use App\Repositories\SmsCreditLedgerRepositoryInterface;
use App\Repositories\UserRepositoryInterface;
use App\Repositories\VisitorRecordRepositoryInterface;
use App\Repositories\WebhookLogRepositoryInterface;
use Illuminate\Support\ServiceProvider;

/**
 * Binds each repository contract to its Eloquent implementation. A test swaps a contract for a mock here.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        MemberRepositoryInterface::class => EloquentMemberRepository::class,
        FamilyRepositoryInterface::class => EloquentFamilyRepository::class,
        DepartmentRepositoryInterface::class => EloquentDepartmentRepository::class,
        FundTypeRepositoryInterface::class => EloquentFundTypeRepository::class,
        PledgeRepositoryInterface::class => EloquentPledgeRepository::class,
        MemberDepartmentRepositoryInterface::class => EloquentMemberDepartmentRepository::class,
        MemberMilestoneRepositoryInterface::class => EloquentMemberMilestoneRepository::class,
        AuditLogRepositoryInterface::class => EloquentAuditLogRepository::class,
        ChurchRepositoryInterface::class => EloquentChurchRepository::class,
        ContributionRepositoryInterface::class => EloquentContributionRepository::class,
        AttendanceRecordRepositoryInterface::class => EloquentAttendanceRecordRepository::class,
        UserRepositoryInterface::class => EloquentUserRepository::class,
        MessageLogRepositoryInterface::class => EloquentMessageLogRepository::class,
        ConsentRepositoryInterface::class => EloquentConsentRepository::class,
        ChurchPurgeRepositoryInterface::class => EloquentChurchPurgeRepository::class,
        ServiceRepositoryInterface::class => EloquentServiceRepository::class,
        SmsCreditLedgerRepositoryInterface::class => EloquentSmsCreditLedgerRepository::class,
        ServiceScheduleRepositoryInterface::class => EloquentServiceScheduleRepository::class,
        VisitorRecordRepositoryInterface::class => EloquentVisitorRecordRepository::class,
        WebhookLogRepositoryInterface::class => EloquentWebhookLogRepository::class,
        HandledWhatsappMessageRepositoryInterface::class => EloquentHandledWhatsappMessageRepository::class,
    ];
}
