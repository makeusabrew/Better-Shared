{extends file='base.tpl'}
{block name='body'}
    <h1>View Favourite</h1>
    @{$favourite->author_username}: {$favourite->text}
    <div class='noFavourited'>
        This tweet has been favourited
        {if $user->isAuthed()}
            by
            <a href="{$current_url}/users">{$noFavourites} {if $userFavourited}other {/if}user{if $noFavourites != 1}s{/if}</a>
        {else}
            by {$noFavourite} user{if $noFavourites != 1}s{/if}
        {/if}
    </div>

    <div class='digest'>
        {foreach from=$favourite->getDigests() item="digest"}
            {$digest->title}
            {$digest->getBody()|nl2br}
        {/foreach}
    </div>
{/block}
