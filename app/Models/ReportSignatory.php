<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ReportSignatory extends Model
{
    protected $fillable = ['label', 'name', 'title'];

    public static function ordered(): Collection
    {
        return static::orderBy('position')->get();
    }
}
