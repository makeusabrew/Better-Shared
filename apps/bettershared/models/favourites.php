<?php

class Favourite extends Object {
    public function getNumberOfTimesFavourited() {
        $count = Table::factory('UserFavourites')->countAll(array(
            'favourite_id' => $this->getId(),
        ));
        return $count;
    }
}

class Favourites extends Table {
    protected $meta = array(
        'columns' => array(
            'created_at' => array(
                'type' => 'text',
            ),
            'twitter_id' => array(
                'type' => 'text',
            ),
            'text' => array(
                'type' => 'text',
            ),
            'author_username' => array(
                'type' => 'text',
            ),
            'author_id' => array(
                'type' => 'text',
            ),
            'source' => array(
                'type' => 'text',
            ),
            'reply_username' => array(
                'type' => 'text',
            ),
            'reply_id' => array(
                'type' => 'text',
            ),
            'entities' => array(
                'type' => 'text',
            ),
        ),
    );

    public function findByTwitterId($id) {
        return $this->find(array(
            'twitter_id' => $id,
        ));
    }

    public function findAllForUser($user_id) {
        $sql = "SELECT ".$this->getColumnString("f")." FROM `favourites` f
        INNER JOIN `user_favourites`
        ON (f.id=user_favourites.favourite_id)
        WHERE user_favourites.user_id = ?";

        $params = array($user_id);

		$dbh = Db::getInstance();
		$sth = $dbh->prepare($sql);
		$sth->setFetchMode(PDO::FETCH_CLASS, "Favourite");
        $sth->execute($params);
        return $sth->fetchAll();
    }
}
