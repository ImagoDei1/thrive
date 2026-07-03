<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

trait HasConfirmModal
{
    public bool $showConfirm = false;
    public string $confirmAction = '';
    public string $confirmId = '';
    public string $confirmTitle = 'Are you sure?';
    public string $confirmBody = '';
    public string $confirmLabel = 'Confirm';
    public string $confirmClass = 'btn-error';

    public function askConfirm(
        string $action,
        string $title,
        string $body = '',
        string $id = '',
        string $label = 'Confirm',
        string $class = 'btn-error'
    ): void {
        $this->confirmAction = $action;
        $this->confirmId     = $id;
        $this->confirmTitle  = $title;
        $this->confirmBody   = $body;
        $this->confirmLabel  = $label;
        $this->confirmClass  = $class;
        $this->showConfirm   = true;
    }

    public function runConfirmed(): void
    {
        $this->showConfirm = false;
        $action = $this->confirmAction;
        $id     = $this->confirmId;

        $this->confirmAction = '';
        $this->confirmId     = '';

        if ($id !== '') {
            $this->$action($id);
        } else {
            $this->$action();
        }
    }
}
