{**
 * plugins/generic/reviewerCertificate/templates/myCertificates.tpl
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * My Certificates listing page for reviewers
 *}
{include file="frontend/components/header.tpl" pageTitle="plugins.generic.reviewerCertificate.myCertificates.title"}

<div class="page page_my_certificates">
	<div class="container">
		<h1>{translate key="plugins.generic.reviewerCertificate.myCertificates.title"}</h1>
		<p>{translate key="plugins.generic.reviewerCertificate.myCertificates.description"}</p>

		{if $certificateEmailSent}
			<div class="certificate-email-banner certificate-email-banner-success" role="status">
				{translate key="plugins.generic.reviewerCertificate.myCertificates.emailSent"}
			</div>
		{/if}
		{if $certificateEmailError}
			<div class="certificate-email-banner certificate-email-banner-error" role="alert">
				{translate key="plugins.generic.reviewerCertificate.myCertificates.emailError"}
			</div>
		{/if}

		{if $certificates|@count > 0}
			<table class="certificate-list-table">
				<thead>
					<tr>
						<th>{translate key="plugins.generic.reviewerCertificate.myCertificates.dateIssued"}</th>
						<th>{translate key="plugins.generic.reviewerCertificate.myCertificates.submissionTitle"}</th>
						<th>{translate key="plugins.generic.reviewerCertificate.myCertificates.certificateCode"}</th>
						<th>{translate key="plugins.generic.reviewerCertificate.myCertificates.actions"}</th>
					</tr>
				</thead>
				<tbody>
					{foreach from=$certificates item=cert}
						<tr>
							<td>{$cert.dateIssued|escape}</td>
							<td>{$cert.submissionTitle|escape}</td>
							<td><code>{$cert.certificateCode|escape}</code></td>
							<td>
								<a href="{$cert.downloadUrl}" class="certificate-download-btn" target="_blank">
									{translate key="plugins.generic.reviewerCertificate.downloadCertificate"}
								</a>
								<form class="certificate-email-form" method="post" action="{$cert.emailUrl}">
									{csrf}
									<button type="submit" class="certificate-email-btn">
										{translate key="plugins.generic.reviewerCertificate.myCertificates.emailAction"}
									</button>
								</form>
							</td>
						</tr>
					{/foreach}
				</tbody>
			</table>
		{else}
			<p class="no-certificates">{translate key="plugins.generic.reviewerCertificate.myCertificates.noCertificates"}</p>
		{/if}
	</div>
</div>

{include file="frontend/components/footer.tpl"}
