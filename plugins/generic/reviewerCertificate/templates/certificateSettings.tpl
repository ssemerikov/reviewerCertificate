{**
 * plugins/generic/reviewerCertificate/templates/certificateSettings.tpl
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * Certificate settings form template
 *}
<script>
	$(function() {ldelim}
		$('#certificateSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="certificateSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}" enctype="multipart/form-data">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="certificateSettingsFormNotification"}

	<div id="description">
		<p>{translate key="plugins.generic.reviewerCertificate.settings.description"}</p>
	</div>

	{fbvFormArea id="certificateTemplateSettings" title="plugins.generic.reviewerCertificate.settings.templateSettings"}

		{* Background Image Upload *}
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.backgroundImage" description="plugins.generic.reviewerCertificate.settings.backgroundImageDescription"}
			<input type="file" id="backgroundImage" name="backgroundImage" accept="image/*" class="pkp_form_file" />
			{if $backgroundImage}
				<p class="description">{translate key="plugins.generic.reviewerCertificate.settings.currentImage"}: {$backgroundImageName}</p>
			{/if}
		{/fbvFormSection}

		{* Header Text *}
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.headerText" description="plugins.generic.reviewerCertificate.settings.headerTextDescription" required=true}
			{fbvElement type="text" id="headerText" value=$headerText maxlength="255" size=$fbvStyles.size.LARGE}
		{/fbvFormSection}

		{* Body Template *}
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.bodyTemplate" description="plugins.generic.reviewerCertificate.settings.bodyTemplateDescription" required=true}
			{fbvElement type="textarea" id="bodyTemplate" value=$bodyTemplate height=$fbvStyles.height.TALL rich=false}
			<p class="description">
				{translate key="plugins.generic.reviewerCertificate.settings.defaultTemplate"}:
				<a href="#" onclick="$('#bodyTemplate').val($('#defaultBodyTemplate').val()); return false;">
					{translate key="plugins.generic.reviewerCertificate.settings.useDefaultTemplate"}
				</a>
			</p>
			<textarea id="defaultBodyTemplate" style="display:none;">{$defaultBodyTemplate}</textarea>
		{/fbvFormSection}

		{* Footer Text *}
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.footerText" description="plugins.generic.reviewerCertificate.settings.footerTextDescription"}
			{fbvElement type="textarea" id="footerText" value=$footerText height=$fbvStyles.height.SHORT}
		{/fbvFormSection}

		{* Available Variables *}
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.availableVariables"}
			<div class="pkp_helpers_clear">
				<p class="description">{translate key="plugins.generic.reviewerCertificate.settings.availableVariablesDescription"}</p>
				<ul class="template-variables">
					{foreach from=$templateVariables item=variable}
						<li><code>{$variable}</code></li>
					{/foreach}
				</ul>
			</div>
		{/fbvFormSection}

	{/fbvFormArea}

	{fbvFormArea id="certificateAppearance" title="plugins.generic.reviewerCertificate.settings.appearance"}

		{* Font Family *}
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.fontFamily"}
			{fbvElement type="select" id="fontFamily" from=$fontOptions selected=$fontFamily translate=false defaultValue="helvetica"}
		{/fbvFormSection}

		{* Font Size *}
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.fontSize"}
			{fbvElement type="text" id="fontSize" value=$fontSize|default:12 size=$fbvStyles.size.SMALL}
		{/fbvFormSection}

		{* Text Color *}
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.textColor" description="plugins.generic.reviewerCertificate.settings.textColorDescription"}
			<div class="pkp_helpers_clear">
				<div style="float:left; margin-right: 10px;">
					<label for="textColorR">R:</label>
					{fbvElement type="text" id="textColorR" value=$textColorR|default:0 size=$fbvStyles.size.SMALL inline=true}
				</div>
				<div style="float:left; margin-right: 10px;">
					<label for="textColorG">G:</label>
					{fbvElement type="text" id="textColorG" value=$textColorG|default:0 size=$fbvStyles.size.SMALL inline=true}
				</div>
				<div style="float:left;">
					<label for="textColorB">B:</label>
					{fbvElement type="text" id="textColorB" value=$textColorB|default:0 size=$fbvStyles.size.SMALL inline=true}
				</div>
			</div>
		{/fbvFormSection}

	{/fbvFormArea}

	{fbvFormArea id="certificateEligibility" title="plugins.generic.reviewerCertificate.settings.eligibility"}

		{* Minimum Reviews *}
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.minimumReviews" description="plugins.generic.reviewerCertificate.settings.minimumReviewsDescription" required=true}
			{fbvElement type="text" id="minimumReviews" value=$minimumReviews|default:1 size=$fbvStyles.size.SMALL}
		{/fbvFormSection}

		{* Include QR Code *}
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.verification" list=true}
			{fbvElement type="checkbox" id="includeQRCode" value="1" checked=$includeQRCode label="plugins.generic.reviewerCertificate.settings.includeQRCode"}
		{/fbvFormSection}

	{/fbvFormArea}

	{fbvFormButtons}
	<p>
		<a href="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="preview"}" target="_blank" class="pkp_button">
			{translate key="plugins.generic.reviewerCertificate.settings.previewCertificate"}
		</a>
	</p>
</form>

<style>
.template-variables {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 5px;
	list-style: none;
	padding: 10px;
	background: #f5f5f5;
	border-radius: 3px;
}

.template-variables li {
	font-size: 0.9em;
}

.template-variables code {
	background: #fff;
	padding: 2px 5px;
	border-radius: 2px;
	font-family: monospace;
}
</style>
