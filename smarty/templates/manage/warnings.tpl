<h2>{gettext text='Warnings'}</h2><br>
{if $message}
	{$message}
	<br/>
	<br/>
{/if}
<form action="manage_page.php" method="post" name="warningform">
	<fieldset>
		<label for="board">{gettext text='Board'}</label>
		{$boardListDropdown}
		<br/>
		<input type="hidden" name="action" value="warnings" />
		<label for="post_id">{gettext text='Post ID'}:</label>
		<input id="post_id" type="text" name="warningpost" value="{$warningPost}">
		<br/>
		<input type="submit" name="getip"  value="{gettext text='Get IP'}">
	</fieldset>
	<fieldset>
		<legend>{gettext text='IP'}</legend>
		<label for="ip">{gettext text='IP'}:</label>
		<input id="ip" type="text" name="ip" value="{$warningIp}"/>
	</fieldset>
	<fieldset>
		<legend>{gettext text='Warning on'}</legend>
		<label for="warningforall"><b>{gettext text='All boards'}</b></label>
		<input id="warningforall" type="checkbox" name="global" {$globalChecked}><br/><hr><br/>
		{$boardListCheckboxes}
	</fieldset>
	<fieldset>
		<legend>{gettext text='Warning text'}</legend>
		<label for="text">{gettext text='Text'}:</label>
		<textarea name="text" id="text" rows="12" cols="80">{$text}</textarea>
		<div class="desc">
			{gettext text='Message to user. Wakaba-mark is supported. Post links with boards specified are recommended (if not specified, ip lookup field or first "Warning on" checkbox or first board to which moderator has access is used).'}
		</div>
		<br/>
		<label for="modnote">{gettext text='Moderator Note'}:</label>
		<input id="modnote" type="text" name="note" value="{$note}" /><div class="desc">Note to moderators</div><br/>
	</fieldset>
	<input type="submit" name="issue" value="{gettext text='Issue warning'}"/>

	<hr/><br/>

	{if $showDeleteAllViewed}
		<br/>
		<input type="submit" name="delete_all_viewed" value=" {gettext text='Delete all viewed'}"/>'
	{/if}
</form>
<br><b>Issued warnings:</b><br>
<table border="1" width="100%"><tr><th>IP Address</th><th>Boards</th><th>Text</th><th>Moderator Note</th><th>Date Added</th><th>Added By</th><th>Viewed</th><th>&nbsp;</th></tr>
	{foreach from=$warnings item=warning}
		<tr>
			<td><a href="?action=bans&ip={$warning.ip}">{$warning.ip}</a></td>
			<td>
				{if $warning.global}
					<b>All boards</b>
				{else}
					{assign var="comma" value=""}
					{strip}
						{foreach from=$warning.boards item=board}
							{$comma}<b>/{$board}/</b>
							{assign var="comma" value=", "}
						{/foreach}
					{/strip}
				{/if}
			</td>
			<td>{$warning.text}</td>
			<td>{$warning.note}</td>
			<td>{$warning.at}</td>
			<td>{$warning.by}</td>
			<td>{$warning.viewed}</td>
			<td>
				<form action="manage_page.php" method="post">
					<input type="hidden" name="action" value="warnings"/>
					<input type="hidden" name="delwarning" value="{$warning.id}"/>
					<input class="tableaction" type="submit" value="{gettext text='Delete'}"/>
				</form>
			</td>
		</tr>
	{/foreach}
</table>
Last <a href="?action=warnings&getwarnings=10">10</a>, <a href="?action=warnings&getwarnings=20">20</a>, <a href="?action=warnings&getwarnings=30">30</a> Warnings
| <a href="?action=warnings">All</a> | <a href="?action=warnings&viewed=1">Viewed</a> | <a href="?action=warnings&viewed=0">Not viewed</a>