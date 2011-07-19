{extends file='base.tpl'}
{block name='body'}
    <h1>Users who've favourited this tweet</h1>
    {foreach from=$users item="_user"}
        <div>
            {$_user->username}
        </div>
    {/foreach}
{/block}
