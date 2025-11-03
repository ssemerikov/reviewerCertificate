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
		// Flag to track if file is selected
		var fileSelected = false;

		// Initially setup as AJAX form
		$('#certificateSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');

		// Handle background image preview and file selection
		$('#backgroundImage').on('change', function(e) {ldelim}
			var file = e.target.files[0];
			if (file) {ldelim}
				// Validate file type
				if (!file.type.match('image.*')) {ldelim}
					alert('Please select an image file (JPEG or PNG)');
					$(this).val('');
					return;
				{rdelim}
				// Validate file size (max 5MB)
				if (file.size > 5 * 1024 * 1024) {ldelim}
					alert('Image file size must be less than 5MB');
					$(this).val('');
					return;
				{rdelim}
				// Show filename
				var fileName = file.name;
				if ($('#imagePreviewText').length === 0) {ldelim}
					$(this).after('<p id="imagePreviewText" class="description" style="color: #28a745; margin-top: 5px;"></p>');
				{rdelim}
				$('#imagePreviewText').text('Selected: ' + fileName);

				// Disable AJAX submission when file is selected
				fileSelected = true;
				console.log('ReviewerCertificate: File selected, will use regular form submission');
			{rdelim} else {ldelim}
				fileSelected = false;
				if ($('#imagePreviewText').length) {ldelim}
					$('#imagePreviewText').remove();
				{rdelim}
			{rdelim}
		{rdelim});

		// Intercept form submission BEFORE AjaxFormHandler
		$('#certificateSettingsForm').on('submit.fileUpload', function(e) {ldelim}
			if (fileSelected) {ldelim}
				console.log('ReviewerCertificate: File selected - destroying AjaxFormHandler for regular submission');
				// Stop event propagation to prevent AjaxFormHandler from handling it
				e.stopImmediatePropagation();

				// Unbind AjaxFormHandler
				$(this).off('.pkpHandler');

				// Submit form normally
				console.log('ReviewerCertificate: Submitting form with regular POST');
				this.submit();

				// Prevent default to avoid double submission
				return false;
			{rdelim}
			// For non-file submissions, let AJAX handler process it
		{rdelim});

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

		// Use attribute selectors to handle OJS's dynamic ID suffixes
		var $rInput = $('input[id^="textColorR"]');
		var $gInput = $('input[id^="textColorG"]');
		var $bInput = $('input[id^="textColorB"]');

		// Set initial color picker value from RGB inputs
		var r = parseInt($rInput.val()) || 0;
		var g = parseInt($gInput.val()) || 0;
		var b = parseInt($bInput.val()) || 0;
		$('#colorPicker').val(rgbToHex(r, g, b));
		$('#colorPreview').css('background-color', rgbToHex(r, g, b));

		// Update RGB values when color picker changes
		// Use 'change' and 'blur' events to ensure values are set before form serialization
		$('#colorPicker').on('input change blur', function() {ldelim}
			var rgb = hexToRgb($(this).val());
			if (rgb) {ldelim}
				$rInput.val(rgb.r);
				$gInput.val(rgb.g);
				$bInput.val(rgb.b);
				$('#colorPreview').css('background-color', $(this).val());
			{rdelim}
		{rdelim});

		// Update color picker when RGB values change manually
		$rInput.add($gInput).add($bInput).on('input change blur', function() {ldelim}
			// Get values and constrain to 0-255 range
			var r = Math.max(0, Math.min(255, parseInt($rInput.val()) || 0));
			var g = Math.max(0, Math.min(255, parseInt($gInput.val()) || 0));
			var b = Math.max(0, Math.min(255, parseInt($bInput.val()) || 0));

			// Update the input values if they were out of range
			$rInput.val(r);
			$gInput.val(g);
			$bInput.val(b);

			// Update color picker and preview
			$('#colorPicker').val(rgbToHex(r, g, b));
			$('#colorPreview').css('background-color', rgbToHex(r, g, b));
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
				<a href="#" onclick="$('[id^=bodyTemplate]').not('#defaultBodyTemplate').val($('#defaultBodyTemplate').val()); return false;">
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

<!-- Certificate Statistics -->
<div class="section" style="margin-top: 40px;">
	<h2>{translate key="plugins.generic.reviewerCertificate.statistics.title"}</h2>
	<p class="description">{translate key="plugins.generic.reviewerCertificate.statistics.description"}</p>

	<div class="statistics-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0;">
		<div class="stat-card" style="background: #f5f5f5; padding: 20px; border-radius: 5px; text-align: center;">
			<h3 style="font-size: 2em; margin: 0; color: #007bff;">{$totalCertificates|default:0}</h3>
			<p style="margin: 5px 0 0 0; color: #666;">{translate key="plugins.generic.reviewerCertificate.statistics.totalCertificates"}</p>
		</div>
		<div class="stat-card" style="background: #f5f5f5; padding: 20px; border-radius: 5px; text-align: center;">
			<h3 style="font-size: 2em; margin: 0; color: #28a745;">{$totalDownloads|default:0}</h3>
			<p style="margin: 5px 0 0 0; color: #666;">{translate key="plugins.generic.reviewerCertificate.statistics.totalDownloads"}</p>
		</div>
		<div class="stat-card" style="background: #f5f5f5; padding: 20px; border-radius: 5px; text-align: center;">
			<h3 style="font-size: 2em; margin: 0; color: #ffc107;">{$uniqueReviewers|default:0}</h3>
			<p style="margin: 5px 0 0 0; color: #666;">{translate key="plugins.generic.reviewerCertificate.statistics.uniqueReviewers"}</p>
		</div>
	</div>
</div>

<!-- Batch Certificate Generation -->
<div class="section" style="margin-top: 40px; padding: 20px; background: #f9f9f9; border-radius: 5px;">
	<h2>{translate key="plugins.generic.reviewerCertificate.batch.title"}</h2>
	<p class="description">{translate key="plugins.generic.reviewerCertificate.batch.description"}</p>

	<form id="batchGenerateForm" style="margin-top: 20px;">
		<div style="margin-bottom: 15px;">
			<label>{translate key="plugins.generic.reviewerCertificate.batch.selectReviewers"}</label>
			<select id="batchReviewers" name="reviewerIds[]" multiple size="10" style="width: 100%; padding: 10px;">
				{if $eligibleReviewers}
					{foreach from=$eligibleReviewers item=reviewer}
						<option value="{$reviewer.id}">{$reviewer.name} ({$reviewer.completedReviews} {translate key="plugins.generic.reviewerCertificate.batch.completedReviews"})</option>
					{/foreach}
				{else}
					<option disabled>{translate key="plugins.generic.reviewerCertificate.batch.noEligibleReviewers"}</option>
				{/if}
			</select>
			<p class="description" style="margin-top: 5px;">{translate key="plugins.generic.reviewerCertificate.batch.selectMultipleHint"}</p>
		</div>

		<button type="button" id="generateBatchBtn" class="pkp_button" style="background: #28a745; color: white;">
			{translate key="plugins.generic.reviewerCertificate.batch.generate"}
		</button>
		<span id="batchProgress" style="margin-left: 15px; display: none;">
			<span class="pkp_spinner"></span> {translate key="plugins.generic.reviewerCertificate.batch.generating"}
		</span>
	</form>

	<div id="batchResult" style="margin-top: 20px; display: none;"></div>
</div>

<script>
$(document).ready(function() {ldelim}
	$('#generateBatchBtn').on('click', function() {ldelim}
		var selectedReviewers = $('#batchReviewers').val();
		if (!selectedReviewers || selectedReviewers.length === 0) {ldelim}
			alert('{translate key="plugins.generic.reviewerCertificate.batch.noSelection" escape="js"}');
			return;
		{rdelim}

		// Show progress
		$('#batchProgress').show();
		$('#generateBatchBtn').prop('disabled', true);

		// Get CSRF token from the form
		var csrfToken = $('#certificateSettingsForm input[name="csrfToken"]').val();

		// Debug logging
		if (console && console.log) {ldelim}
			console.log('Batch certificate generation started');
			console.log('Selected reviewers:', selectedReviewers);
			console.log('CSRF token present:', !!csrfToken);
		{rdelim}

		$.ajax({ldelim}
			url: '{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="generateBatch" escape=false}',
			type: 'POST',
			data: {ldelim}
				reviewerIds: selectedReviewers,
				csrfToken: csrfToken
			{rdelim},
			success: function(response) {ldelim}
				$('#batchProgress').hide();
				$('#generateBatchBtn').prop('disabled', false);

				// Debug logging
				if (console && console.log) {ldelim}
					console.log('Batch generation response:', response);
				{rdelim}

				try {ldelim}
					var data = typeof response === 'string' ? JSON.parse(response) : response;
					if (data.status) {ldelim}
						var generatedCount = data.content && data.content.generated ? data.content.generated : 0;
						$('#batchResult').html(
							'<div style="padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; color: #155724;">' +
							'<strong>Success!</strong> ' + generatedCount + ' {translate key="plugins.generic.reviewerCertificate.batch.certificatesGenerated" escape="js"}' +
							'</div>'
						).show();
						// Reload page after 2 seconds to refresh statistics
						setTimeout(function() {ldelim} location.reload(); {rdelim}, 2000);
					{rdelim} else {ldelim}
						$('#batchResult').html(
							'<div style="padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24;">' +
							'<strong>Error:</strong> ' + (data.content || 'Failed to generate certificates') +
							'</div>'
						).show();
					{rdelim}
				{rdelim} catch (e) {ldelim}
					if (console && console.error) {ldelim}
						console.error('Failed to parse response:', e);
					{rdelim}
					$('#batchResult').html(
						'<div style="padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24;">' +
						'<strong>Error:</strong> Invalid response format' +
						'</div>'
					).show();
				{rdelim}
			{rdelim},
			error: function(xhr, status, error) {ldelim}
				$('#batchProgress').hide();
				$('#generateBatchBtn').prop('disabled', false);

				// Debug logging
				if (console && console.error) {ldelim}
					console.error('Batch generation failed:', status, error);
					console.error('Response:', xhr.responseText);
				{rdelim}

				$('#batchResult').html(
					'<div style="padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24;">' +
					'<strong>Error:</strong> {translate key="plugins.generic.reviewerCertificate.batch.error" escape="js"}' +
					'</div>'
				).show();
			{rdelim}
		{rdelim});
	{rdelim});
{rdelim});
</script>

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
