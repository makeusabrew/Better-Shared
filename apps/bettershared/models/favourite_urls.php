<?php

class FavouriteUrl extends Object {
}

class FavouriteUrls extends Table {
    protected $meta = array(
        'columns' => array(
            'favourite_id' => array(
                'type' => 'foreign_key',
                'table' => 'Favourites',
            ),
            'url' => array(
                'type' => 'text',
            ),
            'display_url' => array(
                'type' => 'text',
            ),
            'expanded_url' => array(
                'type' => 'text',
            ),
            'indices' => array(
                'type' => 'text',
            ),
        ),
    );
}
