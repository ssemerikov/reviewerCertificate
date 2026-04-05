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
