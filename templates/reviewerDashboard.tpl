{**
 * plugins/generic/reviewerCertificate/templates/reviewerDashboard.tpl
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * Certificate download button for reviewer dashboard
 *}

{if $showCertificateButton && $certificateUrl}
	<div class="reviewer-certificate-section" style="margin: 20px 0; padding: 15px; background: #f0f8ff; border: 1px solid #b0d4f1; border-radius: 5px;">
		<h3 style="margin-top: 0; color: #0066cc;">
			<span class="fa fa-certificate" style="margin-right: 8px;"></span>
			{translate key="plugins.generic.reviewerCertificate.certificateAvailable"}
		</h3>
		<p style="margin: 10px 0;">
			{translate key="plugins.generic.reviewerCertificate.certificateAvailableDescription"}
		</p>
		<a href="{$certificateUrl}" class="pkp_button certificate-download-button" target="_blank" style="display: inline-block; margin-top: 10px; padding: 10px 20px; background: #0066cc; color: white; text-decoration: none; border-radius: 3px;">
			<span class="fa fa-download" style="margin-right: 5px;"></span>
			{translate key="plugins.generic.reviewerCertificate.downloadCertificate"}
		</a>
		{if !$certificateExists}
			<p class="description" style="margin-top: 10px; font-size: 0.9em; color: #666;">
				{translate key="plugins.generic.reviewerCertificate.certificateWillBeGenerated"}
			</p>
		{/if}
	</div>
{/if}
