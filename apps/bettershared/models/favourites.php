<?php

class Favourite extends Object {
    public function getNumberOfTimesFavourited() {
        $count = Table::factory('UserFavourites')->countAll(array(
            'favourite_id' => $this->getId(),
        ));
        return $count;
    }

    public function getDigests() {
        $urls = Table::factory('FavouriteUrls')->findAll(array(
            'favourite_id' => $this->getId(),
        ));
        $digests = array();
        foreach ($urls as $url) {
            $target = isset($url->expanded_url) ? $url->expanded_url : $url->url;
            $digest = Table::factory('FavouriteUrlDigests')->findForUrl($url->getId());
            if ($digest === false) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $target);
                //curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies.txt');
                //curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies.txt');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
                curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
                curl_setopt($ch, CURLOPT_USERAGENT, "Better Shared Bot");
                $result = curl_exec($ch);

                $dom = new DomDocument();
                try {
                    $dom->loadHTML($result);
                } catch (ErrorException $e) {
                    // grim. we have to ignore parse errors
                }
                $title = $dom->getElementsByTagName('title')->item(0)->nodeValue;

                $digest = Table::factory('FavouriteUrlDigests')->newObject();
                $digest->setValues(array(
                    'favourite_url_id' => $url->getId(),
                    'title' => $title,
                    'content' => $result,
                ));
                $digest->save();
            }
            $digests[] = $digest;
            return $digests;
        }
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
