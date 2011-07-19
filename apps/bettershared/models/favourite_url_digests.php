<?php

class FavouriteUrlDigest extends Object {
    public function getBody() {
        $dom = new DomDocument();
        try {
            $dom->loadHTML($this->content);
        } catch (Exception $e) {
            // have to ignore :(
        }
        $body = $dom->getElementsByTagName('body')->item(0);
        $doc = new DomDocument();
        foreach ($body->childNodes as $child) {
            $doc->appendChild($doc->importNode($child, true));
        }
        return $doc->saveHTML();
    }
}

class FavouriteUrlDigests extends Table {
    protected $meta = array(
        'columns' => array(
            'favourite_url_id' => array(
                'type' => 'foreign_key',
                'table' => 'UrlFavourites',
            ),
            'title' => array(
                'type' => 'text',
            ),
            'content' => array(
                'type' => 'text',
            ),
        ),
    );

    public function findForUrl($url_id) {
        return $this->find(array(
            'favourite_url_id' => $url_id,
        ));
    }
}
