<?php

namespace Models;

use Enjoin;

class RatersReplies extends Definition
{

    public $table = 'raters_replies';

    public function getAttributes()
    {
        return [
            'id' => ['type' => Enjoin::Integer()],
            'raters_id' => ['type' => Enjoin::Integer()],
            'replies_id' => ['type' => Enjoin::Integer()],
            'value' => ['type' => Enjoin::Integer()]
        ];
    }

    public $timestamps = false;

    public function getRelations()
    {
        return [
            Enjoin::belongsTo(Enjoin::get('Replies'), ['foreignKey' => 'replies_id']),
            Enjoin::belongsTo(Enjoin::get('Raters'), ['foreignKey' => 'raters_id'])
        ];
    }

}
