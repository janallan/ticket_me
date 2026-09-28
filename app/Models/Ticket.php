<?php

namespace App\Models;

use App\Enums\Permission;
use Carbon\CarbonImmutable;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $subject
 * @property string|null $description
 * @property int $department_id
 * @property int $ticket_status_id
 * @property int $ticket_priority_id
 * @property int $requester_id
 * @property int|null $assignee_id
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $last_activity_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $number
 * @property-read Department $department
 * @property-read TicketStatus $status
 * @property-read TicketPriority $priority
 * @property-read User $requester
 * @property-read User|null $assignee
 */
#[Fillable(['subject', 'description', 'department_id', 'ticket_status_id', 'ticket_priority_id', 'requester_id', 'assignee_id', 'closed_at', 'last_activity_at'])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * Get the ticket number shown to users, such as "#000123".
     *
     * @return Attribute<string, never>
     */
    protected function number(): Attribute
    {
        return Attribute::get(fn (): string => static::formatNumber($this->id));
    }

    /**
     * Format a ticket ID as its display number.
     */
    public static function formatNumber(int $id): string
    {
        return '#'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<TicketStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'ticket_status_id');
    }

    /**
     * @return BelongsTo<TicketPriority, $this>
     */
    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'ticket_priority_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Get the replies and internal notes, oldest first. The original description is on the ticket itself.
     *
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->oldest()->orderBy('id');
    }

    /**
     * Get the files attached when the ticket was opened, which belong to the description.
     *
     * @return HasMany<TicketAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class)->whereNull('ticket_message_id');
    }

    /**
     * Determine whether the ticket is in a status that counts as closed.
     */
    public function isClosed(): bool
    {
        return $this->status->is_closed;
    }

    /**
     * Determine whether the given user opened the ticket.
     */
    public function isRequestedBy(User $user): bool
    {
        return $this->requester_id === $user->id;
    }

    /**
     * Scope a query to the tickets the user may see: their own, those in departments where they
     * work tickets, or every ticket for holders of `tickets-all`.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->can(Permission::TicketsAll->value)) {
            return;
        }

        $query->where(function (Builder $query) use ($user) {
            $query->where('requester_id', $user->id);

            if ($user->can(Permission::Tickets->value)) {
                $query->orWhereIn('department_id', $user->departments()->select('departments.id'));
            }
        });
    }
}
