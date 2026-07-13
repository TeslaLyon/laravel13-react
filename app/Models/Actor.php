<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Cog\Contracts\Love\Reactable\Models\Reactable as ReactableInterface;
use Cog\Laravel\Love\Reactable\Models\Traits\Reactable;

class Actor extends Model implements ReactableInterface
{
    use Reactable;
    protected $casts = [
        'booty_img' => 'array',
    ];
}
