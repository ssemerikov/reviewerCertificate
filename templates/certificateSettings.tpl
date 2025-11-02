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

		// Initialize color picker from RGB values
		function rgbToHex(r, g, b) {ldelim}
			return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
		{rdelim}

		function hexToRgb(hex) {ldelim}
			var result = /^#?([a-f\d]{ldelim}2{rdelim})([a-f\d]{ldelim}2{rdelim})([a-f\d]{ldelim}2{rdelim})$/i.exec(hex);
			return result ? {ldelim}
				r: parseInt(result[1], 16),
				g: parseInt(result[2], 16),
				b: parseInt(result[3], 16)
			{rdelim} : null;
		{rdelim}

		// Set initial color picker value from RGB inputs
		var r = parseInt($('#textColorR').val()) || 0;
		var g = parseInt($('#textColorG').val()) || 0;
		var b = parseInt($('#textColorB').val()) || 0;
		$('#colorPicker').val(rgbToHex(r, g, b));
		$('#colorPreview').css('background-color', rgbToHex(r, g, b));

		// Update RGB values when color picker changes
		$('#colorPicker').on('input change', function() {ldelim}
			var rgb = hexToRgb($(this).val());
			if (rgb) {ldelim}
				$('#textColorR').val(rgb.r).trigger('change');
				$('#textColorG').val(rgb.g).trigger('change');
				$('#textColorB').val(rgb.b).trigger('change');
				$('#colorPreview').css('background-color', $(this).val());
			{rdelim}
		{rdelim});

		// Update color picker when RGB values change manually
		$('#textColorR, #textColorG, #textColorB').on('input change', function() {ldelim}
			var r = parseInt($('#textColorR').val()) || 0;
			var g = parseInt($('#textColorG').val()) || 0;
			var b = parseInt($('#textColorB').val()) || 0;
			$('#colorPicker').val(rgbToHex(r, g, b));
			$('#colorPreview').css('background-color', rgbToHex(r, g, b));
		{rdelim});

		// Ensure RGB values are updated from color picker before form submit
		$('#certificateSettingsForm').on('submit', function() {ldelim}
			var colorPickerVal = $('#colorPicker').val();
			if (colorPickerVal) {ldelim}
				var rgb = hexToRgb(colorPickerVal);
				if (rgb) {ldelim}
					$('#textColorR').val(rgb.r);
					$('#textColorG').val(rgb.g);
					$('#textColorB').val(rgb.b);
				{rdelim}
			{rdelim}
		{rdelim});
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
				<div style="margin-bottom: 10px;">
					<label for="colorPicker">Color Picker:</label>
					<input type="color" id="colorPicker" style="width: 60px; height: 30px; border: 1px solid #ccc; cursor: pointer;" />
					<span id="colorPreview" style="display: inline-block; width: 30px; height: 30px; border: 1px solid #ccc; margin-left: 5px; vertical-align: middle;"></span>
				</div>
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
