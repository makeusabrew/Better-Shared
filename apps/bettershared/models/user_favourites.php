<?php

class UserFavourite extends Object {
}

class UserFavourites extends Table {
    protected $meta = array(
        'columns' => array(
            'user_id' => array(
                'type' => 'foreign_key',
                'table' => 'Users'
            ),
            'favourite_id' => array(
                'type' => 'foreign_key',
                'table' => 'Favourites',
            ),
        ),
    );
}
