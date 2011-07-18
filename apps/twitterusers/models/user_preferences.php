<?php

class UserPreference extends Object {
}

class UserPreferences extends Table {
    protected $meta = array(
        'columns' => array(
            'user_id' => array(
                'type' => 'foreign_key',
                'table' => 'Users',
            ),
            'key' => array(
                'type' => 'text',
            ),
            'value' => array(
                'type' => 'text',
            ),
        ),
    );

    public function findAllForUser($user_id) {
        return $this->findAll(array(
            'user_id' => $user_id,
        ));
    }
}
