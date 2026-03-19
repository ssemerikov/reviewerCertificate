<?php
/**
 * @file plugins/generic/reviewerCertificate/index.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @ingroup plugins_generic_reviewerCertificate
 * @brief Wrapper for Reviewer Certificate plugin
 */
require_once('ReviewerCertificatePlugin.php');
return new \APP\plugins\generic\reviewerCertificate\ReviewerCertificatePlugin();
