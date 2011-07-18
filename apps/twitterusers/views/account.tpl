{extends file="bettershared/views/base.tpl"}
{block name="title"}My Account | {$smarty.block.parent}{/block}
{block name="body"}
    <h2>My Account</h2>

    <form action="/me/update" method="post">
        <label for="email">Email Address</label>
        <input id="email" name="email" type="email" value="{$user->getPreference('email')|htmlentities8}" />

        <label for="email_digests">Email me new digests?</label>
        <input id="email_digests" name="email_digests" type="checkbox"{if $user->getPreference('email_digests')} checked=""{/if} />

        <input type="submit" value="Update" />
    </form>
{/block}
