<div class="replymode">Результаты поиска</div>
{if $qty > 0}<b>Найдено {$qty}:</b>
<hr>
<br>
{foreach from=$results item=result}
<!-- {$result.__number} --> {if $result.archived == 1}(В архиве) {$result.posted_date} <a href="{$ku_webpath}/{$result.board}/arch/res/{if $result.board_parent_id > 0}{$result.board_parent_id}{else}{$result.board_id}{/if}.html#{$result.board_id}">{$result.board}: {$result.board_id}
{else}{$result.posted_date} <a href="{$ku_webpath}/{$result.board}/res/{if $result.board_parent_id > 0}{$result.board_parent_id}{else}{$result.board_id}{/if}.html#{$result.board_id}">{$result.board}: {$result.board_id}{/if}
</a>:<br>
{$result.message}<br>
<small>{$result.score}</small>
<hr>
{/foreach}
{else}<b>Ничего не найдено</b>{/if}<br>
