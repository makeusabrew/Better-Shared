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
}
