<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{setting value="site.title"}{if isset($title)}: - {$title}{/if}</title>
</head>
<body>
    {if isset($messages) && count($messages)}
        <div id='messageOuter'>
            {foreach from=$messages item="message"}
                <div class='message'>
                    {$message}
                </div>
            {/foreach}
        </div>
    {/if}
    <ul id="nav">
        <li><a href="/">home</a></li>
        {if $user->isAuthed()}
            <li><a href="/me">settings</a></li>
        {else}
            <li><a href="/login">login</a></li>
        {/if}
    </ul>
    {block name="body"}<p>Your body content goes here.</p>{/block}

    {*
      ordinarily body will probably be wrapped with surrounding markup, so it
      makes sense to have a separate block to put script tags in
     *}
    {block name="script"}{/block}
</body>
</html>
