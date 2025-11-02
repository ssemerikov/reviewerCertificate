{**
 * plugins/generic/reviewerCertificate/templates/verify.tpl
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * Certificate verification page
 *}
{include file="frontend/components/header.tpl" pageTitle="plugins.generic.reviewerCertificate.verify.title"}

<div class="page page_certificate_verify">
	<div class="container">
		<h1>{translate key="plugins.generic.reviewerCertificate.verify.title"}</h1>

		<div class="certificate-verify-content">
			{if $certificateCode}
				{* Verification result for certificate accessed via QR code *}
				{if $isValid}
					<div class="certificate-verify-result success">
						<h2>{translate key="plugins.generic.reviewerCertificate.verify.valid"}</h2>
						<div class="certificate-details">
							<p><strong>{translate key="plugins.generic.reviewerCertificate.verify.certificateCode"}:</strong> {$certificateCode}</p>
							<p><strong>{translate key="plugins.generic.reviewerCertificate.verify.reviewerName"}:</strong> {$reviewerName}</p>
							<p><strong>{translate key="plugins.generic.reviewerCertificate.verify.journalName"}:</strong> {$journalName}</p>
							<p><strong>{translate key="plugins.generic.reviewerCertificate.verify.dateIssued"}:</strong> {$dateIssued|date_format:"%B %e, %Y"}</p>
						</div>
					</div>
				{else}
					<div class="certificate-verify-result error">
						<h2>{translate key="plugins.generic.reviewerCertificate.verify.invalid"}</h2>
						<p>{translate key="plugins.generic.reviewerCertificate.verify.invalidDescription"}</p>
					</div>
				{/if}
			{else}
				{* Manual verification form *}
				<div class="certificate-verify-form">
					<p>{translate key="plugins.generic.reviewerCertificate.verify.description"}</p>
					<form method="get" action="{url page="certificate" op="verify"}">
						<div class="form-group">
							<label for="code">{translate key="plugins.generic.reviewerCertificate.verify.code"}</label>
							<input type="text" id="code" name="code" class="form-control" required maxlength="12" />
						</div>
						<button type="submit" class="btn btn-primary">
							{translate key="plugins.generic.reviewerCertificate.verify.button"}
						</button>
					</form>
				</div>
			{/if}
		</div>
	</div>
</div>

<style>
.certificate-verify-content {
	max-width: 600px;
	margin: 2em auto;
	padding: 2em;
	background: #f9f9f9;
	border-radius: 5px;
}

.certificate-verify-result {
	padding: 2em;
	border-radius: 5px;
	margin: 1em 0;
}

.certificate-verify-result.success {
	background: #d4edda;
	border: 1px solid #c3e6cb;
	color: #155724;
}

.certificate-verify-result.error {
	background: #f8d7da;
	border: 1px solid #f5c6cb;
	color: #721c24;
}

.certificate-details {
	margin-top: 1.5em;
}

.certificate-details p {
	margin: 0.5em 0;
	line-height: 1.6;
}

.certificate-verify-form {
	padding: 1em;
}

.form-group {
	margin-bottom: 1em;
}

.form-group label {
	display: block;
	margin-bottom: 0.5em;
	font-weight: bold;
}

.form-control {
	width: 100%;
	padding: 0.5em;
	border: 1px solid #ccc;
	border-radius: 3px;
	font-size: 1em;
}

.btn {
	padding: 0.7em 1.5em;
	border: none;
	border-radius: 3px;
	cursor: pointer;
	font-size: 1em;
}

.btn-primary {
	background: #007bff;
	color: white;
}

.btn-primary:hover {
	background: #0056b3;
}
</style>

{include file="frontend/components/footer.tpl"}
