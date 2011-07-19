<?php
require_once('apps/twitterusers/deps/twitter-async/EpiCurl.php');
require_once('apps/twitterusers/deps/twitter-async/EpiOAuth.php');
require_once('apps/twitterusers/deps/twitter-async/EpiTwitter.php');
require_once('apps/bettershared/controllers/abstract.php');

class BettersharedController extends AbstractController {
    public function index() {
        if ($this->user->isAuthed()) {
            $this->assign('favourites', $this->user->getFavourites());
        }
    }

    public function view_favourite() {
        $favourite = Table::factory('Favourites')->read($this->getMatch('id'));
        $this->assign('favourite', $favourite);
        $noFavourites = $favourite->getNumberOfTimesFavourited();
        if ($this->user->isAuthed()) {
            if ($this->user->hasFavouriteId($favourite->getId())) {
                $noFavourites --;
                $this->assign('userFavourited', true);
            } else {
                $this->assign('userFavourited', false);
            }
        }
        $this->assign('noFavourites', $noFavourites);
    }

    public function view_favourite_users() {
        $favourite = Table::factory('Favourites')->read($this->getMatch('id'));
        $users = Table::factory('Users')->findAllForFavourite($favourite->getId());
        $this->assign('users', $users);
    }
}
