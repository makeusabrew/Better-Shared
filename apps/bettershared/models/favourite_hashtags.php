<?php

class FavouriteHashtag extends Object {
}

class FavouriteHashtags extends Table {
    protected $meta = array(
        'columns' => array(
            'favourite_id' => array(
                'type' => 'foreign_key',
                'table' => 'Favourites',
            ),
            'text' => array(
                'type' => 'text',
            ),
            'indices' => array(
                'type' => 'text',
            ),
        ),
    );
}
