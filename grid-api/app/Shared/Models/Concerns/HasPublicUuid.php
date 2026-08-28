<?php

namespace App\Shared\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasPublicUuid
{
    public function initializeHasPublicUuid(): void
    {
        $this->makeHidden($this->getKeyName());
    }

    protected static function bootHasPublicUuid(): void
    {
        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('id'))) {
                $model->setAttribute('id', (string) Str::uuid());
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
