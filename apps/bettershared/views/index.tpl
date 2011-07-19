{extends file="base.tpl"}
{block name="body"}
    <h1>{setting value="site.title"}</h1>
    {if $user->isAuthed()}
        {foreach from=$favourites item="favourite"}
            <div><span class="author">@{$favourite->author_username}:</span> <a href="/favourite/{$favourite->getId()}">{$favourite->text}</a></div>
        {/foreach}
    {else}
        <p>Please login.</p>
    {/if}
{/block}
