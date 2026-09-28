<div>
    <form wire:submit="reply" class="space-y-4">
        <flux:textarea wire:model="replyForm.body" :label="$replyForm['isInternal'] ? __('Internal note') : __('Reply')" rows="5" required
            :placeholder="$replyForm['isInternal'] ? __('Only people who work this ticket will see this note.') : __('Write a reply...')" />

        <flux:card>
            <x-ticket-attachments-input model="replyForm.attachments" :files="$replyForm['attachments']" />
        </flux:card>

        <div class="flex flex-wrap items-center justify-between gap-4">
            @if ($this->canWork)
                <flux:switch wire:model.live="replyForm.isInternal" :label="__('Internal note')" align="left" />
            @else
                <span></span>
            @endif

            <flux:button variant="primary" type="submit" data-test="send-reply-button">
                {{ $replyForm['isInternal'] ? __('Add note') : __('Send reply') }}
            </flux:button>
        </div>
    </form>
</div>
