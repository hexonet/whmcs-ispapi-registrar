{assign var=ispapioptslabel value="Ispapi-Options"} 
{assign var=ispapinamelabel value="Ispapi-Name"} 

{assign var=localpresence value="Local Presence"}
{assign var=sec3agreement value="Section 3 Agreement"}
{assign var=sec5agreement value="Section 5 Agreement"}
{assign var=sec6agreement value="Section 6 Agreement"}
{assign var=sec7agreement value="Section 7 Agreement"}

{literal}
<script>
	$( document ).ready(function() {
		$(".X-IT-ACCEPT-TRUSTEE-TAC").val({/literal}{$smarty.post.additionalfields.$localpresence}{literal});
		$(".X-IT-PIN").val({/literal}{$smarty.post.additionalfields.PIN}{literal});
		$(".X-IT-ACCEPT-LIABILITY-TAC").val({/literal}{$smarty.post.additionalfields.$sec3agreement}{literal});
		$(".X-IT-ACCEPT-REGISTRATION-TAC").val({/literal}{$smarty.post.additionalfields.$sec5agreement}{literal});
		$(".X-IT-ACCEPT-DIFFUSION-AND-ACCESSIBILITY-TAC").val({/literal}{$smarty.post.additionalfields.$sec6agreement}{literal});
		$(".X-IT-ACCEPT-EXPLICIT-TAC").val({/literal}{$smarty.post.additionalfields.$sec7agreement}{literal});
	});
</script>
{/literal}


{include file="$template/pageheader.tpl" title="Change of Registrant - $domain"}
<br>
.IT Change of Registrant requires a special procedure called TRADE. Registrant and Admin contact will be overwritten. 
<br><br>

{if $successful}
	<div class="alert alert-success textcenter">
    	<p><b>The TRADE has been sended successfully.</b></p>
	</div>
{/if}

{if $error}
	<div class="alert alert-error textcenter">
	<p>{$error}</p>
	</div>
{/if}

{literal}
<style>
	.controls blockquote {
		margin-left:20px;
		margin-top:10px;
	}
</style>
{/literal}
<BR>
<form method="POST" action="{$smarty.server.PHP_SELF}">
<input type="hidden" name="action" value="domaindetails">
<input type="hidden" name="id" value="{$domainid}">
<input type="hidden" name="modop" value="custom">
<input type="hidden" name="a" value="registrantmodification_it">
<input type="hidden" name="submit" value="1">
<fieldset id="Registrantmodification" class="onecol">
	{foreach key=name item=value from=$values.Registrant}
	    <div class="control-group">
		    <label class="control-label" for="contactdetails[Registrant][{$name}]">{$name}</label>
			<div class="controls">
			    <input type="text" name="contactdetails[Registrant][{$name}]" id="contactdetails[Registrant][{$name}]" value="{$value}" size="30" />
			</div>
		</div>
	{/foreach}
	
	{foreach item=field from=$additionalfields}
	
		{if $field.Type == "dropdown"}
			{assign var="options" value=","|explode:$field.Options}
			{assign var="ispapioptions" value=","|explode:$field.$ispapioptslabel}
			{assign var="ispapiname" value=""}
			<div class="control-group">
	 			<label class="control-label" for="additionalfields[{$field.Name}]">{$field.Name}<br></label>
	 			<div class="controls">
					<select name="additionalfields[{$field.Name}]" id="additionalfields[{$field.Name}]" class="{$field.$ispapinamelabel}">
						{foreach from=$options key=k  item=option}
			        		<option value="{$ispapioptions.$k}">{$option}</option>
			        	{/foreach}
			    	</select>
			    	{$field.Description}
		    	</div>
		    </div>
	    {/if}
		{if $field.Type == "text"}
			<div class="control-group">
				<label class="control-label" for="additionalfields[{$field.Name}]">{$field.Name}</label>
				
				<div class="controls">
					<input name="additionalfields[{$field.Name}]" id="additionalfields[{$field.Name}]" type="text" class="{$field.$ispapinamelabel}">
				</div>
			</div>
		{/if}
	{/foreach}

	<div class="control-group">
		<label class="control-label" for=""></label>
		
		<div class="controls">
			<input class="btn btn-large btn-primary" type="submit" value="Send TRADE">
		</div>
	</div>
</fieldset>
</form>

<form method="post" action="{$smarty.server.PHP_SELF}?action=domaindetails">
	<input type="hidden" name="id" value="{$domainid}" />
	<p><input type="submit" value="{$LANG.clientareabacklink}" class="btn" /></p>
</form>