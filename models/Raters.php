<?php

namespace Models;

use Enjoin;

class Raters extends Definition
{

    public $table = 'raters';

    public function getAttributes()
    {
        return [
            'id' => ['type' => Enjoin::Integer()],
            'email' => ['type' => Enjoin::String()],
            'token' => ['type' => Enjoin::String()],
            'ttl' => ['type' => Enjoin::String()],
        ];
    }

    public $timestamps = false;

    public function getRelations()
    {
        return [
            Enjoin::hasMany(Enjoin::get('RatersReplies'), ['foreignKey' => 'raters_id'])
        ];
    }

}
