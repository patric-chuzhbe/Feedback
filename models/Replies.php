<?php

namespace Models;

use Enjoin;

class Replies extends Definition
{

    public $table = 'replies';

    public $expanseModel = Expanse\RepliesModel::class;


    public function getAttributes()
    {
        return [
            'id' => ['type' => Enjoin::Integer()],
            'on_state' => ['type' => Enjoin::Boolean()],
            'pages_id' => ['type' => Enjoin::Integer()],
            'name' => [
                'type' => Enjoin::String(),
                'validate' => 'between:0,255'
            ],
            'town' => [
                'type' => Enjoin::String(),
                'validate' => 'between:0,255'
            ],
            'content' => [
                'type' => Enjoin::String(),
                'validate' => 'required'
            ],
            'type' => [
                'type' => Enjoin::Enum('pos', 'neg'),
                'validate' => 'required'
            ]
        ];
    }

    public function getRelations()
    {
        return [
            Enjoin::belongsTo(Enjoin::get('Pages'), ['foreignKey' => 'pages_id']),
            Enjoin::hasMany(Enjoin::get('RatersReplies'), ['foreignKey' => 'replies_id'])
        ];
    }

} // end of class
