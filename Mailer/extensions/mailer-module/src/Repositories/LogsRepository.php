<?php
declare(strict_types=1);

namespace Remp\MailerModule\Repositories;

use Nette\Utils\DateTime;

class LogsRepository extends Repository
{
    protected $tableName = 'mail_logs';

    protected $dataTableSearchable = ['email'];

    private $eventMap = [
        'delivered' => 'delivered_at',
        'clicked' => 'clicked_at',
        'opened' => 'opened_at',
        'complained' => 'spam_complained_at',
        'bounced' => 'hard_bounced_at',
        'failed' => 'dropped_at',
        'dropped' => 'dropped_at',
    ];

    private $bouncesMap = [
        'suppress-bounce' => 'hard_bounced_at',
        'suppress-complaint' => 'hard_bounced_at',
        'suppress-unsubscribe' => 'hard_bounced_at',
    ];

    public function allForEmail(string $email): Selection
    {
        return $this->allForEmails([$email]);
    }

    /**
     * @param array<string> $emails
     */
    public function allForEmails(array $emails): Selection
    {
        return $this->getTable()->where('email', $emails);
    }

    public function add(string $email, string $subject, int $templateId, ?int $jobId = null, ?int $batchId = null, ?string $mailSenderId = null, ?int $attachmentSize = null, ?string $context = null): ActiveRow
    {
        return $this->insert(
            $this->getInsertData($email, $subject, $templateId, $jobId, $batchId, $mailSenderId, $attachmentSize, $context)
        );
    }

    public function getInsertData(string $email, string $subject, int $templateId, ?int $jobId = null, ?int $batchId = null, ?string $mailSenderId = null, ?int $attachmentSize = null, ?string $context = null): array
    {
        return [
            'email' => $email,
            'subject' => $subject,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
            'mail_template_id' => $templateId,
            'mail_job_id' => $jobId,
            'mail_job_batch_id' => $batchId,
            'mail_sender_id' => $mailSenderId,
            'attachment_size' => $attachmentSize,
            'context' => $context,
        ];
    }

    /**
     * @param array<string> $emails
     */
    public function deleteAllForEmails(array $emails): int
    {
        if (count($emails) === 0) {
            return 0;
        }

        return $this->getTable()->where([
            'email' => $emails
        ])->delete();
    }

    public function getEmailLogs(string $email): Selection
    {
        return $this->getTable()->where('email', $email)->order('created_at DESC');
    }

    public function getJobLogs(int $jobId): Selection
    {
        return $this->getTable()->where('mail_job_id', $jobId)->order('created_at DESC');
    }

    public function findBySenderId(string $senderId): ?ActiveRow
    {
        return $this->getTable()->where('mail_sender_id', $senderId)->limit(1)->fetch();
    }

    public function getBatchTemplateStats(ActiveRow $batchTemplate): ?ActiveRow
    {
        $columns = [
            'mail_job_batch_id',
            'COUNT(delivered_at) AS delivered',
            'COUNT(dropped_at) AS dropped',
            'COUNT(spam_complained_at) AS spam_complained',
            'COUNT(hard_bounced_at) AS hard_bounced',
            'COUNT(clicked_at) AS clicked',
            'COUNT(opened_at) AS opened'
        ];
        return $this->getTable()
            ->select(implode(',', $columns))
            ->where([
                'mail_job_batch_id' => $batchTemplate->mail_job_batch_id,
                'mail_template_id' => $batchTemplate->mail_template_id,
            ])
            ->limit(1)
            ->fetch();
    }

    public function getNonBatchTemplateStats(array $templateIds): ?ActiveRow
    {
        $columns = [
            'COUNT(created_at) AS sent',
            'COUNT(delivered_at) AS delivered',
            'COUNT(dropped_at) AS dropped',
            'COUNT(spam_complained_at) AS spam_complained',
            'COUNT(hard_bounced_at) AS hard_bounced',
            'COUNT(clicked_at) AS clicked',
            'COUNT(opened_at) AS opened',
            'COUNT(:mail_log_conversions.converted_at) AS converted',
        ];
        return $this->getTable()
            ->select(implode(',', $columns))
            ->where([
                'mail_template_id' => $templateIds,
                'mail_job_batch_id IS NULL',
            ])
            ->limit(1)
            ->fetch();
    }

    public function tableFilter(
        string $query,
        string $order,
        string $orderDirection,
        ?int $limit = null,
        ?int $offset = null,
        ?int $templateId = null,
        ?DateTime $createdAtFrom = null,
        ?DateTime $createdAtTo = null
    ): Selection {
        $selection = $this->getTable()
            ->order($order . ' ' . strtoupper($orderDirection));

        if ($templateId !== null) {
            $selection->where('mail_template_id = ?', $templateId);
        }

        if ($createdAtFrom !== null) {
            $selection->where('created_at >= ?', $createdAtFrom);
        }

        if ($createdAtTo !== null) {
            $selection->where('created_at <= ?', $createdAtTo);
        }

        if (!empty($query)) {
            $selection->where('email = ?', $query);
        }

        if ($limit !== null) {
            $selection->limit($limit, $offset);
        }

        return $selection;
    }

    public function alreadySentForJob(string $email, int $jobId): bool
    {
        return $this->getTable()->where([
            'mail_logs.mail_job_id' => $jobId,
            'mail_logs.email' => $email
        ])->count('*') > 0;
    }

    public function alreadySentForEmail(string $mailTemplateCode, string $email): bool
    {
        return $this->getTable()->where([
            'mail_logs.email' => $email,
            'mail_template.code' => $mailTemplateCode
        ])->count('*') > 0;
    }

    public function filterAlreadySent(array $emails, string $mailTemplateCode, int $jobId, ?string $context = null): array
    {
        $query = $this->getTable()->where([
            'mail_logs.email' => $emails,
            'mail_template.code' => $mailTemplateCode
        ])->whereOr([
            'mail_logs.email' => $emails,
            'mail_logs.mail_job_id' => $jobId,
        ]);

        if ($context) {
            $query->whereOr([
                'mail_logs.email' => $emails,
                'mail_logs.context' => $context,
            ]);
        }

        $alreadySentEmails = $query->select('email')->fetchPairs(null, 'email');

        return array_diff($emails, $alreadySentEmails);
    }

    public function alreadySentContext(string $email, string $context): bool
    {
        return $this->getTable()->where([
            'mail_logs.email' => $email,
            'mail_logs.context' => $context,
        ])->count('*') > 0;
    }

    public function mappedEvents(): array
    {
        return array_keys($this->eventMap);
    }

    public function mapEvent(string $externalEvent, ?string $reason): ?string
    {
        if (!isset($this->eventMap[$externalEvent])) {
            return null;
        }
        if ($externalEvent === 'failed' && in_array($reason, $this->bouncesMap, true)) {
            return $this->bouncesMap[$reason];
        }
        return $this->eventMap[$externalEvent];
    }
}
