<?php

class User extends Object {
    /**
     * keep track of whether this user is authed (logged in)
     * or not
     */
    protected $isAuthed = false;

    /**
     * bung this user's ID in the session
     */
    public function addToSession() {
        Log::debug('adding user ID ['.$this->getId().'] to session, setting cookie identifier ['.$this->identifier.']');
        $s = Session::getInstance();
        $s->user_id = $this->getId();

        // cookie piggybacks too!
        CookieJar::getInstance()->setCookie('iv_identifier', $this->identifier, (time()+31536000));
    }
    
    /**
     * remove this user from the session
     */
    public function logout() {
    	$s = Session::getInstance();
    	unset($s->user_id);
        CookieJar::getInstance()->setCookie('iv_identifier', "", time()-3600);
        $this->setAuthed(false);
    }

    /**
     * is this user authenticated?
     */
    public function isAuthed() {
        return $this->isAuthed;
    }

    /**
     * update this user's authed state
     */
    public function setAuthed($authed) {
        $this->isAuthed = $authed;
    }

    public function isAccount() {
        return $this->type === 'account';
    }

    public function isGuest() {
        return $this->type === 'guest';
    }

    public function hasVotedOnQuestion($question) {
        $vote = Table::factory('Votes')->find(array(
            'user_id' => $this->getId(),
            'question_id' => $question->getId(),
        ));
        return $vote ? true : false;
    }

    public function convertVotesToUser($user_id) {
        $sql = "UPDATE `votes` SET `user_id` = ? WHERE `user_id` = ?";
        $params = array($user_id, $this->getId());
		$dbh = Db::getInstance();
		$sth = $dbh->prepare($sql);
        $sth->execute($params);
    }

    public function hasRetweetedQuestion($question) {
        $retweet = Table::factory('Retweets')->find(array(
            'user_id' => $this->getId(),
            'question_id' => $question->getId(),
        ));
        return $retweet ? true : false;
    }

    public function hasFavouriteId($favourite_id) {
        $favourite = Table::factory('UserFavourites')->find(array(
            'user_id' => $this->getId(),
            'favourite_id' => $favourite_id,
        ));
        return $favourite ? true : false;
    }

    public function addFavouriteId($favourite_id) {
        $userFavourite = Table::factory('UserFavourites')->newObject();
        $userFavourite->setValues(array(
            'user_id' => $this->getId(),
            'favourite_id' => $favourite_id,
        ));
        $userFavourite->save();
        Log::debug('adding favourite ID ['.$favourite_id.'] to user\'s list');
        return $userFavourite->getId();
    }

    public function getFavourites() {
        return Table::factory('Favourites')->findAllForUser($this->getId());
    }
}

class Users extends Table {

    protected $meta = array(
        "columns" => array(
            "type" => array(
                "type" => "text",
            ),
            "username" => array(
                "type" => "text",
            ),
            "profile_image_url" => array(
                "type" => "text",
            ),
            "twitter_id" => array(
                "type" => "text",
            ),
            "oauth_token" => array(
                "type" => "text",
            ),
            "oauth_token_secret" => array(
                "type" => "text",
            ),
            "identifier" => array(
                "type" => "text",
            ),
        ),
    );

    public function loadFromSession() {
        $s = Session::getInstance();
        $id = $s->user_id;
        if ($id === NULL) {
            return new User();
        }
        $user = $this->read($id);
        if (!$user) {
            // oh dear
            Log::debug("Could not find user id [".$id."]");
            return new User();
        }
        $user->setAuthed(true);
        return $user;
    }

    public function loginWithIdentifier() {
        $identifier = CookieJar::getInstance()->getCookie('iv_identifier');

        if ($identifier === null) {
            // oh well, cya
            return new User();
        }
        $user = $this->find(array(
            'identifier' => $identifier,
        ));
        if ($user === false) {
            Log::debug('could not find user for identifier ['.$identifier.']');
            return new User();
        }
        $user->setAuthed(true);
        return $user;
    }


    public function findByTokens($token, $secret) {
        return $this->find(array(
            'oauth_token' => $token,
            'oauth_token_secret' => $secret,
        ));
    }

    public function findByTwitterId($twitter_id) {
        return $this->find(array(
            'twitter_id' => $twitter_id,
        ));
    }

    public function createNewGuestAccount() {
        $user = $this->newObject();
        $user->setValues(array(
            "type" => "guest",
            "identifier" => sha1(mt_rand()."salted"),
        ));
        $user->save();
        return $user;
    }
}
