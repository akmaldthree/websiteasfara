<?php

namespace App\Services;

use App\Models\ContentEntry;
use App\Models\ContentRevision;
use App\Models\Page;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class EditorialWorkflow
{
    public const APPROVAL_REQUIRED = 'Akun Anda dapat menulis dan mengajukan konten, tetapi tidak dapat menyetujuinya.';

    public function save(ContentEntry|Page $record, array $data, User $actor): void
    {
        $status = $data['editorial_status'];
        if ($status === 'approved' && ! $actor->allowed('content.approve')) {
            throw ValidationException::withMessages(['editorial_status' => self::APPROVAL_REQUIRED]);
        }

        $draft = $record->editorial_draft ?? [];
        $reviewInProgress = ($draft['editorial_status'] ?? $record->editorial_status) === 'review';
        $data += match ($status) {
            'draft' => ['submitted_by' => null, 'submitted_at' => null, 'reviewed_by' => null, 'reviewed_at' => null],
            'review' => [
                'submitted_by' => $reviewInProgress ? ($draft['submitted_by'] ?? $record->submitted_by) : $actor->id,
                'submitted_at' => $reviewInProgress ? ($draft['submitted_at'] ?? $record->submitted_at) : now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
            ],
            'approved' => [
                'submitted_by' => $draft['submitted_by'] ?? $record->submitted_by ?? $actor->id,
                'submitted_at' => $draft['submitted_at'] ?? $record->submitted_at ?? now(),
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ],
        };

        if ($record->exists) {
            ContentRevision::create([
                'content_type' => $record instanceof Page ? 'pages' : 'content',
                'content_id' => $record->id,
                'user_id' => $actor->id,
                'snapshot' => $record->getAttributes(),
            ]);
        }

        if ($record->active && $record->editorial_status === 'approved' && $status !== 'approved') {
            $record->update(['editorial_draft' => array_replace($draft, $data)]);

            return;
        }

        $record->fill($data + ['editorial_draft' => null])->save();
    }
}
