{section name=page start=0 loop=$pages}
[{if $smarty.section.page.index == $page}<strong>{$smarty.section.page.index}</strong>]
{else}
<a href="search.php?q={$query|escape}&p={$smarty.section.page.index}{if $inboards}{foreach from=$inboards item=value key=board}&inboards[]={$board}{/foreach}{/if}">{$smarty.section.page.index}</a>]&nbsp;{/if}
{/section}
<div class="footer" style="clear: both;">- 410chan -</div>
</body>
</html>
