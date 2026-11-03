<?php

namespace App\Services\Crm;

use App\Models\Contact;
use App\Models\ContactEvent;
use App\Models\ContactTask;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Dashboard → Clientes, by hand: prospects added before they buy, edits to a customer's
 * profile, what happened with them (notes, calls, WhatsApps) and follow-ups with a date.
 * Contacts created from orders stay ContactResolver's job.
 */
class ContactService
{
    public function __construct(private LifecycleClassifier $stages)
    {
    }

    public function create(Store $store, array $data, ?int $userId): Contact
    {
        $attributes = $this->clean($store, $data, null);

        return DB::transaction(function () use ($store, $attributes, $data, $userId) {
            $contact = $store->contacts()->create($attributes + ['source' => 'manual']);
            $this->stages->apply($contact);
            if (filled($data['first_note'] ?? null)) {
                $this->log($contact, 'note', $data['first_note'], $userId);
            }

            return $contact;
        });
    }

    public function update(Contact $contact, array $data): Contact
    {
        $contact->update($this->clean($contact->store, $data, $contact));

        return $contact;
    }

    public function log(Contact $contact, string $type, ?string $body, ?int $userId, ?Carbon $at = null): ContactEvent
    {
        return ContactEvent::create([
            'store_id'    => $contact->store_id,
            'contact_id'  => $contact->id,
            'user_id'     => $userId,
            'type'        => $type,
            'body'        => $body,
            'occurred_at' => $at ?? now(),
        ]);
    }

    public function addTask(Store $store, ?Contact $contact, array $data, ?int $userId): ContactTask
    {
        return ContactTask::create([
            'store_id'   => $store->id,
            'contact_id' => $contact?->id,
            'user_id'    => $userId,
            'product_id' => $data['product_id'] ?? null,
            'kind'       => $data['kind'] ?? 'follow_up',
            'title'      => trim($data['title']),
            'due_at'     => Carbon::parse($data['due_at']),
        ]);
    }

    /** Done (with an optional outcome written in the customer's timeline) or reopened. */
    public function completeTask(ContactTask $task, ?string $outcome, ?int $userId): void
    {
        DB::transaction(function () use ($task, $outcome, $userId) {
            $task->update(['done_at' => now()]);
            if ($task->contact) {
                $this->log($task->contact, 'task', $task->title . (filled($outcome) ? ' — ' . trim($outcome) : ''), $userId);
            }
        });
    }

    public function reopenTask(ContactTask $task): void
    {
        $task->update(['done_at' => null]);
    }

    /** Normalized attributes; a phone, e-mail or document already used by another contact is refused. */
    private function clean(Store $store, array $data, ?Contact $current): array
    {
        $phone = ContactResolver::normalizePhone($data['phone'] ?? null);
        $email = ContactResolver::normalizeEmail($data['email'] ?? null);
        if (filled($data['phone'] ?? null) && !$phone) {
            throw ValidationException::withMessages(['phone' => 'El celular no es válido.']);
        }

        foreach (['phone' => $phone, 'email' => $email, 'document_number' => $data['document_number'] ?? null] as $column => $value) {
            if (!$value) {
                continue;
            }
            $taken = $store->contacts()->where($column, $value)->when($current, fn ($q) => $q->whereKeyNot($current->id))->first();
            if ($taken) {
                throw ValidationException::withMessages([$column => "Ya tienes a {$taken->name} con ese dato."]);
            }
        }

        $consent = (bool) ($data['accepts_marketing'] ?? false);

        return [
            'name'                 => trim($data['name']),
            'phone'                => $phone,
            'email'                => $email,
            'document_type'        => filled($data['document_number'] ?? null) ? ($data['document_type'] ?? 'dni') : null,
            'document_number'      => filled($data['document_number'] ?? null) ? trim($data['document_number']) : null,
            'city'                 => filled($data['city'] ?? null) ? trim($data['city']) : null,
            'birthday'             => $data['birthday'] ?? null,
            'relationship'         => $data['relationship'] ?? null,
            'notes'                => $data['notes'] ?? null,
            'tags'                 => array_values(array_unique(array_filter(array_map('trim', $data['tags'] ?? [])))) ?: null,
            'accepts_marketing'    => $consent,
            // The moment of consent is kept; it only changes when consent is given again.
            'marketing_consent_at' => $consent ? ($current?->accepts_marketing ? $current->marketing_consent_at : now()) : null,
        ];
    }
}
