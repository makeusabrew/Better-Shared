<?php

class FavouriteUserMention extends Object {
}

class FavouriteUserMentions extends Table {
    protected $meta = array(
        'columns' => array(
            'favourite_id' => array(
                'type' => 'foreign_key',
                'table' => 'Favourites',
            ),
            'author_id' => array(
                'type' => 'text',
            ),
            'screen_name' => array(
                'type' => 'text',
            ),
            'name' => array(
                'type' => 'text',
            ),
            'indices' => array(
                'type' => 'text',
            ),
        ),
    );
}
