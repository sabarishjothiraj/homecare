<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    use HasFactory;

    protected $table = 'configs';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $record = static::where('key', $key)->first();

        return $record?->value ?? $default;
    }

    public static function setValue(string $key, mixed $value): self
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
