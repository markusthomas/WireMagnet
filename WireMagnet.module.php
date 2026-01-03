<?php
namespace ProcessWire;

/**
 * Lead Magnet Manager
 *
 * Handles form rendering, submission processing, and secure file delivery.
 */

class WireMagnet extends WireData implements Module, ConfigurableModule
{

    /**
     * Initialize the module
     */
    public function init()
    {
        // Register a URL hook for the actual file download (e.g., /lead-download/hash123)
        $this->addHook('/lead-download/{token}', $this, 'handleDownloadRequest');

        // Automatically handle form submission before the page is rendered
        $this->addHookBefore('Page::render', $this, 'handleFormSubmission');

        // Register a URL hook for email confirmation (DOI)
        $this->addHook('/lead-confirm/{token}', $this, 'handleConfirmationRequest');
    }

    /**
     * Module configuration fields
     *
     * @param array $data
     * @return InputfieldWrapper
     */
    public static function getModuleConfigInputfields(array $data)
    {
        $inputfields = new InputfieldWrapper();
        $modules = wire('modules');

        // --- General Settings ---
        $fs = $modules->get('InputfieldFieldset');
        $fs->label = __('General Settings', __FILE__);

        $f = $modules->get('InputfieldEmail');
        $f->name = 'sender_email';
        $f->label = __('Sender Email Address', __FILE__);
        $f->value = isset($data['sender_email']) ? $data['sender_email'] : 'noreply@yoursite.com';
        $fs->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'anonymize_ip';
        $f->label = __('Anonymize IP Address (GDPR)', __FILE__);
        $f->description = __('If checked, the IP address will be anonymized before storage (IPv4: last octet masked, IPv6: last 64 bits masked).', __FILE__);
        $f->value = 1;
        if (!empty($data['anonymize_ip']))
            $f->attr('checked', 'checked');
        $fs->add($f);

        $f = $modules->get('InputfieldTextarea');
        $f->name = 'blocked_domains';
        $f->label = __('Blocked Email Domains', __FILE__);
        $f->description = __('Enter one domain per line. Emails from these domains will be rejected.', __FILE__);
        $f->value = isset($data['blocked_domains']) ? $data['blocked_domains'] : "mailinator.com\ntrashmail.com\nyopmail.com\nguerrillamail.com\nsharklasers.com\n10minutemail.com\ntemp-mail.org";
        $fs->add($f);

        $inputfields->add($fs);

        // --- Form Settings ---
        $fs = $modules->get('InputfieldFieldset');
        $fs->label = __('Form Settings', __FILE__);

        $f = $modules->get('InputfieldText');
        $f->name = 'privacy_text';
        $f->label = __('Privacy Consent Text (GDPR)', __FILE__);
        $f->description = __('Label for the mandatory consent checkbox. You can use HTML for links.', __FILE__);
        $f->value = isset($data['privacy_text']) ? $data['privacy_text'] : __('I agree to the storage of my data.', __FILE__);
        $fs->add($f);

        $f = $modules->get('InputfieldText');
        $f->name = 'button_text';
        $f->label = __('Form Button Text', __FILE__);
        $f->description = __('The text displayed on the submit button.', __FILE__);
        $f->value = isset($data['button_text']) ? $data['button_text'] : __('Get Free Download', __FILE__);
        $fs->add($f);

        $f = $modules->get('InputfieldPageListSelect');
        $f->name = 'redirect_page';
        $f->label = __('Custom Thank You Page', __FILE__);
        $f->description = __('Select a page to redirect to after successful submission. If empty, a success message is shown inline.', __FILE__);
        if (isset($data['redirect_page']))
            $f->value = $data['redirect_page'];
        $fs->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'load_alpine';
        $f->label = __('Load Alpine.js', __FILE__);
        $f->description = __('Check this if you want the module to load Alpine.js from a CDN. Uncheck if you already include Alpine.js in your site template.', __FILE__);
        $f->value = 1;
        if (!empty($data['load_alpine']))
            $f->attr('checked', 'checked');
        $fs->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'disable_css';
        $f->label = __('Disable Default CSS', __FILE__);
        $f->description = __('If checked, the module will not load the default CSS styles for the form.', __FILE__);
        $f->value = 1;
        if (!empty($data['disable_css']))
            $f->attr('checked', 'checked');
        $fs->add($f);

        $inputfields->add($fs);

        // --- Download Email Settings ---
        $fs = $modules->get('InputfieldFieldset');
        $fs->label = __('Download Email Settings', __FILE__);

        $f = $modules->get('InputfieldText');
        $f->name = 'email_subject';
        $f->label = __('Download Email Subject', __FILE__);
        $f->value = isset($data['email_subject']) ? $data['email_subject'] : __('Your Download is ready', __FILE__);
        $fs->add($f);

        $f = $modules->get('InputfieldTextarea');
        $f->name = 'download_email_body';
        $f->label = __('Download Email Body (Link)', __FILE__);
        $f->description = __('Text for the email containing the download link. Use {link} as placeholder for the URL.', __FILE__);
        $f->value = isset($data['download_email_body']) ? $data['download_email_body'] : __('Click here to download: {link}', __FILE__);
        $fs->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'attach_file';
        $f->label = __('Attach File to Email', __FILE__);
        $f->description = __('If enabled, the file will be attached directly to the email instead of sending a download link. Warning: Large files may be rejected by email providers.', __FILE__);
        $f->value = 1;
        if (!empty($data['attach_file']))
            $f->attr('checked', 'checked');
        $fs->add($f);

        $f = $modules->get('InputfieldTextarea');
        $f->name = 'download_email_body_attached';
        $f->label = __('Download Email Body (Attachment)', __FILE__);
        $f->description = __('Text for the email when the file is attached directly.', __FILE__);
        $f->value = isset($data['download_email_body_attached']) ? $data['download_email_body_attached'] : __('Please find your requested file attached to this email.', __FILE__);
        $f->showIf = 'attach_file=1';
        $fs->add($f);

        $inputfields->add($fs);

        // --- Double Opt-In (DOI) Settings ---
        $fs = $modules->get('InputfieldFieldset');
        $fs->label = __('Double Opt-In (DOI) Settings', __FILE__);

        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'enable_doi';
        $f->label = __('Enable Double Opt-In (DOI)', __FILE__);
        $f->description = __('If enabled, users must confirm their email address before receiving the download link.', __FILE__);
        $f->value = 1;
        if (!empty($data['enable_doi']))
            $f->attr('checked', 'checked');
        $fs->add($f);

        $f = $modules->get('InputfieldText');
        $f->name = 'confirm_email_subject';
        $f->label = __('Confirmation Email Subject (DOI)', __FILE__);
        $f->value = isset($data['confirm_email_subject']) ? $data['confirm_email_subject'] : __('Please confirm your email address', __FILE__);
        $f->showIf = 'enable_doi=1';
        $fs->add($f);

        $f = $modules->get('InputfieldTextarea');
        $f->name = 'confirm_email_body';
        $f->label = __('Confirmation Email Body (DOI)', __FILE__);
        $f->description = __('Text for the confirmation email. Use {link} as placeholder for the confirmation URL.', __FILE__);
        $f->value = isset($data['confirm_email_body']) ? $data['confirm_email_body'] : __('Please click the following link to confirm your email address and receive your download:', __FILE__) . "\n\n{link}";
        $f->showIf = 'enable_doi=1';
        $fs->add($f);

        $f = $modules->get('InputfieldPageListSelect');
        $f->name = 'doi_redirect_page';
        $f->label = __('Custom DOI Confirmation Page', __FILE__);
        $f->description = __('Select a page to redirect to after successful email confirmation (DOI). If empty, redirects to homepage.', __FILE__);
        if (isset($data['doi_redirect_page']))
            $f->value = $data['doi_redirect_page'];
        $f->showIf = 'enable_doi=1';
        $fs->add($f);

        $inputfields->add($fs);

        return $inputfields;
    }

    /**
     * Hook callback to check and process form submission
     *
     * @param HookEvent $event
     */
    public function handleFormSubmission($event)
    {
        $input = $this->wire('input');

        // Check for POST request and specific action
        if ($input->requestMethod('POST') && $input->post->action === 'subscribe_lead') {
            $result = $this->processSubmission($input);

            // If AJAX, we need to stop rendering and output JSON
            if ($this->config->ajax) {
                header('Content-Type: application/json');
                echo json_encode($result);
                exit;
            }

            // If result is a string, it is an error message. Attach it to the page object.
            if (is_string($result)) {
                $event->object->set('leadMagnetError', $result);
            }
        }
    }

    /**
     * Renders the lead magnet form
     *
     * Usage in template: echo $modules->get('LeadMagnetManager')->renderForm($page);
     *
     * @param Page $magnetPage The page representing the lead magnet
     * @param string $fieldName The name of the file field (default: lead_file)
     * @param string|null $buttonText Optional override for the button text
     * @return string HTML of the form
     */
    public function renderForm(Page $magnetPage, $fieldName = 'lead_file', $buttonText = null)
    {
        $input = $this->wire('input');
        $page = $this->wire('page');

        // Unique form ID to handle multiple forms on one page if necessary
        $formId = 'lead-form-' . $magnetPage->id . '-' . $fieldName;
        $csrfName = $this->session->CSRF->getTokenName();
        $csrfValue = $this->session->CSRF->getTokenValue();
        $privacyText = $this->privacy_text ?: $this->_('I agree to the storage of my data.');
        $btnText = $buttonText ?: ($this->button_text ?: $this->_('Get Free Download'));

        // Add default CSS once
        $script = "";
        if (!$this->disable_css && !defined('LEAD_MAGNET_CSS_LOADED')) {
            define('LEAD_MAGNET_CSS_LOADED', true);
            $script .= "
            <style>
                .lead-magnet-wrapper { max-width: 100%; margin: 1rem 0; }
                .lead-magnet-form { background: #f8f9fa; padding: 1.5rem; border-radius: 0.5rem; border: 1px solid #e9ecef; }
                .lead-magnet-form .form-group { margin-bottom: 1rem; }
                .lead-magnet-form .form-check { margin-bottom: 1rem; display: flex; align-items: flex-start; }
                .lead-magnet-form label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #212529; }
                .lead-magnet-form input[type='email'] { display: block; width: 100%; padding: 0.375rem 0.75rem; font-size: 1rem; line-height: 1.5; color: #495057; background-color: #fff; background-clip: padding-box; border: 1px solid #ced4da; border-radius: 0.25rem; box-sizing: border-box; transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out; }
                .lead-magnet-form input[type='email']:focus { border-color: #80bdff; outline: 0; box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25); }
                .lead-magnet-form button { display: inline-block; font-weight: 400; color: #fff; text-align: center; vertical-align: middle; user-select: none; background-color: #007bff; border: 1px solid #007bff; padding: 0.375rem 0.75rem; font-size: 1rem; line-height: 1.5; border-radius: 0.25rem; transition: color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out; cursor: pointer; }
                .lead-magnet-form button:hover { background-color: #0069d9; border-color: #0062cc; }
                .lead-magnet-form button:disabled { opacity: 0.65; cursor: not-allowed; }
                .lead-magnet-alert { position: relative; padding: 0.75rem 1.25rem; margin-bottom: 1rem; border: 1px solid transparent; border-radius: 0.25rem; }
                .lead-magnet-alert.success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
                .lead-magnet-alert.error { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
                .lead-magnet-form input[type='checkbox'] { margin-right: 0.5rem; margin-top: 0.3rem; width: auto; display: inline-block; flex-shrink: 0; appearance: auto; }
                .lead-magnet-form .form-check label { margin-bottom: 0; font-weight: 400; display: inline; }
            </style>";
        }

        // 1. Handle Success State (URL parameter from non-AJAX redirect)
        if ($input->get->success) {
            return $script . "<div class='lead-magnet-alert success'>" . $this->_('Thanks! Please check your email for the download link.') . "</div>";
        }

        // Alpine Component Data
        $alpineData = [
            'magnetId' => $magnetPage->id,
            'fieldName' => $fieldName,
            'csrfName' => $csrfName,
            'csrfValue' => $csrfValue,
            'endpoint' => $magnetPage->url
        ];
        $jsonConfig = htmlspecialchars(json_encode($alpineData), ENT_QUOTES, 'UTF-8');

        // Check if we need to load Alpine
        if ($this->load_alpine) {
            // Check if already added to prevent duplicates if renderForm called multiple times
            if (!defined('LEAD_MAGNET_ALPINE_LOADED')) {
                define('LEAD_MAGNET_ALPINE_LOADED', true);
                $script .= "<script defer src='https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js'></script>";
            }
        }

        // Inline the component definition once
        if (!defined('LEAD_MAGNET_COMPONENT_DEFINED')) {
            define('LEAD_MAGNET_COMPONENT_DEFINED', true);
            $script .= "
            <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('leadMagnetForm', (config) => ({
                    loading: false,
                    success: false,
                    error: null,
                    successMessage: '',
                    email: '',
                    privacy: false,
                    website: '',

                    submitForm() {
                        this.loading = true;
                        this.error = null;

                        let formData = new FormData();
                        formData.append('action', 'subscribe_lead');
                        formData.append('magnet_id', config.magnetId);
                        formData.append('magnet_field_name', config.fieldName);
                        formData.append('email', this.email);
                        formData.append('privacy', this.privacy ? 1 : 0);
                        formData.append('website', this.website);
                        formData.append(config.csrfName, config.csrfValue);

                        fetch(config.endpoint, {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            this.loading = false;
                            if (data.success) {
                                this.success = true;
                                this.successMessage = data.message;
                                this.email = '';
                                this.privacy = false;
                                if (data.redirect) {
                                    window.location.href = data.redirect;
                                }
                            } else {
                                this.error = data.error || 'Unknown error';
                            }
                        })
                        .catch(err => {
                            this.loading = false;
                            this.error = 'Network error.';
                            console.error(err);
                        });
                    }
                }));
            });
            </script>";
        }

        // 2. Handle Server-Side Error State (from non-AJAX POST)
        $serverError = "";
        if ($page->leadMagnetError) {
            $serverError = "<div class='lead-magnet-alert error'>" . $page->leadMagnetError . "</div>";
        }

        $output = $script;
        $output .= "
        <div x-data=\"leadMagnetForm($jsonConfig)\" class='lead-magnet-wrapper'>
            <div x-show='success' class='lead-magnet-alert success' style='display: none;' x-text='successMessage'></div>
            $serverError

            <form action='./' method='post' class='lead-magnet-form' id='{$formId}' @submit.prevent='submitForm' x-show='!success'>
                <input type='hidden' name='action' value='subscribe_lead'>
                <input type='hidden' name='magnet_id' value='{$magnetPage->id}'>
                <input type='hidden' name='magnet_field_name' value='{$fieldName}'>
                <input type='hidden' name='{$csrfName}' value='{$csrfValue}'>

                <div x-show='error' class='lead-magnet-alert error' style='display: none;' x-text='error'></div>

                <div class='form-group'>
                    <label for='email'>" . $this->_('Email Address') . "</label>
                    <input type='email' name='email' x-model='email' required placeholder='john@example.com'>
                </div>

                <!-- Honeypot Field -->
                <div style='position: absolute; left: -5000px;' aria-hidden='true'>
                    <input type='text' name='website' x-model='website' tabindex='-1' autocomplete='off'>
                </div>

                <div class='form-group form-check'>
                    <input type='checkbox' name='privacy' id='privacy_{$formId}' x-model='privacy' required value='1'>
                    <label for='privacy_{$formId}'>$privacyText</label>
                </div>

                <button type='submit' :disabled='loading'>
                    <span x-show='!loading'>" . $btnText . "</span>
                    <span x-show='loading' style='display: none;'>" . $this->_('Loading...') . "</span>
                </button>
            </form>
        </div>";

        return $output;
    }

    /**
     * Process the form submission
     *
     * Hook this into Page::render or call it manually in the template header
     * @param Input $input
     * @return bool|string|array True on success, error message string on failure, or array for AJAX
     */
    public function processSubmission($input)
    {
        if ($input->post->action !== 'subscribe_lead')
            return false;

        // CSRF Check
        if (!$this->session->CSRF->hasValidToken()) {
            $msg = $this->_("Invalid Token");
            if ($this->config->ajax)
                return ['success' => false, 'error' => $msg];
            return $msg;
        }

        // Check Honeypot
        if (!empty($input->post->website)) {
            $msg = "Spam detected.";
            if ($this->config->ajax)
                return ['success' => false, 'error' => $msg];
            return $msg;
        }

        $email = $this->sanitizer->email($input->post->email);
        $magnetId = (int) $input->post->magnet_id;
        $fieldName = $this->sanitizer->fieldName($input->post->magnet_field_name);

        if (!$email || !$magnetId || !$fieldName) {
            $msg = $this->_("Invalid Input");
            if ($this->config->ajax)
                return ['success' => false, 'error' => $msg];
            return $msg;
        }

        // Check privacy consent
        if (empty($input->post->privacy)) {
            $msg = $this->_("Please agree to the data storage.");
            if ($this->config->ajax)
                return ['success' => false, 'error' => $msg];
            return $msg;
        }

        // Check for disposable email
        if ($this->isDisposableEmail($email)) {
            $msg = $this->_("Please use a valid email address. Disposable email addresses are not allowed.");
            if ($this->config->ajax)
                return ['success' => false, 'error' => $msg];
            return $msg;
        }

        // Check for recent submissions to prevent spam/duplicates
        if ($this->hasRecentSubmission($email, $magnetId, $fieldName)) {
            $msg = $this->_("You have already requested this download in the last 24 hours. Please check your inbox.");
            if ($this->config->ajax)
                return ['success' => false, 'error' => $msg];
            return $msg;
        }

        $sent = false;

        if ($this->enable_doi) {
            // Double Opt-In Process
            $token = bin2hex(random_bytes(32));
            // Save with confirmed = 0
            $this->saveLeadToDatabase($email, $magnetId, $fieldName, 0, $token);
            // Send confirmation email
            $sent = $this->sendConfirmationEmail($email, $token);
        } else {
            // Single Opt-In Process
            // Save with confirmed = 1
            $leadId = $this->saveLeadToDatabase($email, $magnetId, $fieldName, 1);
            // Send download email
            $sent = $this->sendDownloadEmail($email, $magnetId, $leadId, $fieldName);
        }

        if ($sent) {
            $redirectUrl = null;
            if ($this->redirect_page) {
                $p = $this->wire('pages')->get((int) $this->redirect_page);
                if ($p->id && $p->viewable()) {
                    $redirectUrl = $p->url;
                }
            }

            if ($this->config->ajax) {
                $response = ['success' => true, 'message' => $this->_('Thanks! Please check your email for the download link.')];
                if ($redirectUrl)
                    $response['redirect'] = $redirectUrl;
                return $response;
            }

            if ($redirectUrl) {
                $this->session->redirect($redirectUrl);
            } else {
                $this->session->redirect('./?success=1');
            }
        } else {
            $msg = $this->_("Error sending email.");
            if ($this->config->ajax)
                return ['success' => false, 'error' => $msg];
            return $msg;
        }
    }

    /**
     * Generates a unique, temporary token for the download
     * @param int $magnetId
     * @param int $leadId
     * @param string $fieldName
     * @return string The download URL
     */
    protected function generateDownloadToken(int $magnetId, int $leadId = 0, $fieldName = 'lead_file')
    {
        // Automatically clean up expired tokens before generating a new one
        $this->cleanupExpiredTokens();

        // Logic to create a hash and store it with an expiry timestamp
        $token = bin2hex(random_bytes(16));

        // Store token in DB with 24 hour expiry
        $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $sql = "INSERT INTO lead_tokens (token, magnet_id, lead_id, magnet_field_name, expires_at) VALUES (:token, :magnet_id, :lead_id, :magnet_field_name, :expires_at)";
        $query = $this->database->prepare($sql);
        $query->bindValue(':token', $token);
        $query->bindValue(':magnet_id', $magnetId, \PDO::PARAM_INT);
        $query->bindValue(':lead_id', $leadId, \PDO::PARAM_INT);
        $query->bindValue(':magnet_field_name', $fieldName);
        $query->bindValue(':expires_at', $expiry);
        $query->execute();

        return $this->pages->get(1)->httpUrl . "lead-download/" . $token;
    }

    /**
     * Deletes expired tokens from the database
     */
    protected function cleanupExpiredTokens()
    {
        $sql = "DELETE FROM lead_tokens WHERE expires_at < NOW()";
        $this->database->exec($sql);
    }

    /**
     * Called only when the module is installed
     * Creates the database tables
     */
    public function ___install()
    {
        $sql = "CREATE TABLE IF NOT EXISTS leads_archive (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            magnet_id INT UNSIGNED NOT NULL,
            magnet_field_name VARCHAR(128) NOT NULL DEFAULT 'lead_file',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45) DEFAULT NULL,
            confirmed TINYINT UNSIGNED NOT NULL DEFAULT 1,
            confirmation_token VARCHAR(64) DEFAULT NULL,
            download_count INT UNSIGNED NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $this->database->exec($sql);

        $sqlTokens = "CREATE TABLE IF NOT EXISTS lead_tokens (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            token VARCHAR(64) NOT NULL,
            magnet_id INT UNSIGNED NOT NULL,
            magnet_field_name VARCHAR(128) NOT NULL DEFAULT 'lead_file',
            lead_id INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            INDEX (token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->database->exec($sqlTokens);

        // Create 'lead_file' field if it doesn't exist
        $fieldName = 'lead_file';
        $field = $this->fields->get($fieldName);
        if (!$field) {
            $field = new Field();
            $field->type = $this->modules->get("FieldtypeFile");
            $field->name = $fieldName;
            $field->label = 'Lead Magnet File';
            $field->extensions = 'pdf zip rar doc docx';
            $field->maxFiles = 1;
            $field->description = 'Upload the file for the Lead Magnet here.';
            $field->save();
        }

        // Create 'lead-magnet' template if it doesn't exist
        $tplName = 'lead-magnet';
        $template = $this->templates->get($tplName);
        if (!$template) {
            $fg = new Fieldgroup();
            $fg->name = $tplName;
            $fg->add($this->fields->get('title'));
            $fg->add($field);
            $fg->save();

            $template = new Template();
            $template->name = $tplName;
            $template->fieldgroup = $fg;
            $template->save();
        }

        // Create permission for the admin page
        $p = $this->permissions->get('lead-magnet-view');
        if (!$p->id) {
            $p = $this->permissions->add('lead-magnet-view');
            $p->title = 'View Lead Magnet Leads';
            $p->save();
        }
    }

    /**
     * Called only when the module is uninstalled
     * Drops the database tables
     */
    public function ___uninstall()
    {
        $this->database->exec("DROP TABLE IF EXISTS leads_archive");
        $this->database->exec("DROP TABLE IF EXISTS lead_tokens");

        $p = $this->permissions->get('lead-magnet-view');
        if ($p->id) {
            $this->permissions->delete($p);
        }
    }

    /**
     * Checks if the email has already requested this magnet in the last 24 hours
     * @param string $email
     * @param int $magnetId
     * @param string $fieldName
     * @return bool
     */
    protected function hasRecentSubmission($email, $magnetId, $fieldName)
    {
        $sql = "SELECT COUNT(*) FROM leads_archive WHERE email=:email AND magnet_id=:magnet_id AND magnet_field_name=:magnet_field_name AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $query = $this->database->prepare($sql);
        $query->bindValue(':email', $email);
        $query->bindValue(':magnet_id', $magnetId, \PDO::PARAM_INT);
        $query->bindValue(':magnet_field_name', $fieldName);
        $query->execute();

        return (int) $query->fetchColumn() > 0;
    }

    /**
     * Internal method to save the lead
     */
    protected function saveLeadToDatabase($email, $magnetId, $fieldName, $confirmed = 1, $token = null)
    {
        // Automatically clean up old unconfirmed leads
        $this->cleanupUnconfirmedLeads();

        $ip = $this->session->getIP();
        if ($this->anonymize_ip) {
            $ip = $this->anonymizeIP($ip);
        }

        $sql = "INSERT INTO leads_archive (email, magnet_id, magnet_field_name, created_at, ip_address, confirmed, confirmation_token) VALUES (:email, :magnet_id, :magnet_field_name, NOW(), :ip_address, :confirmed, :token)";
        $query = $this->database->prepare($sql);
        $query->bindValue(':email', $email);
        $query->bindValue(':magnet_id', (int) $magnetId, \PDO::PARAM_INT);
        $query->bindValue(':magnet_field_name', $fieldName);
        $query->bindValue(':ip_address', $ip);
        $query->bindValue(':confirmed', $confirmed, \PDO::PARAM_INT);
        $query->bindValue(':token', $token);
        $query->execute();

        $this->log->save('leads', "New Lead: $email for Magnet ID $magnetId ($fieldName)");

        return (int) $this->database->lastInsertId();
    }

    /**
     * Deletes unconfirmed leads older than 48 hours
     */
    protected function cleanupUnconfirmedLeads()
    {
        $sql = "DELETE FROM leads_archive WHERE confirmed=0 AND created_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)";
        $this->database->exec($sql);
    }

    /**
     * Anonymizes an IP address
     * @param string $ip
     * @return string
     */
    protected function anonymizeIP($ip)
    {
        $packed = inet_pton($ip);
        if (false === $packed) {
            return $ip;
        }
        if (4 === strlen($packed)) { // IPv4
            return inet_ntop($packed & "\xff\xff\xff\x00");
        } elseif (16 === strlen($packed)) { // IPv6
            // Mask last 64 bits
            return inet_ntop($packed & "\xff\xff\xff\xff\xff\xff\xff\xff\x00\x00\x00\x00\x00\x00\x00\x00");
        }
        return $ip;
    }

    /**
     * Checks if an email address belongs to a blocked domain
     * @param string $email
     * @return bool
     */
    protected function isDisposableEmail($email)
    {
        $domain = strtolower(substr(strrchr($email, "@"), 1));
        if (!$domain)
            return false;

        $blocked = $this->blocked_domains;
        if ($blocked === null) {
            // Default list if not configured yet
            $blocked = "mailinator.com\ntrashmail.com\nyopmail.com\nguerrillamail.com\nsharklasers.com\n10minutemail.com\ntemp-mail.org";
        }

        $domains = preg_split('/\r\n|\r|\n/', $blocked);
        $domains = array_map('trim', $domains);

        return in_array($domain, $domains);
    }

    /**
     * Internal method to send the email
     */
    protected function sendDownloadEmail($email, $magnetId, $leadId = 0, $fieldName = 'lead_file')
    {
        $sender = $this->sender_email ?: 'noreply@yoursite.com';
        $subject = $this->email_subject ?: $this->_('Your Download is ready');

        $mail = new WireMail();
        $mail->to($email);
        $mail->from($sender);
        $mail->subject($subject);

        $fileAttached = false;

        if ($this->attach_file) {
            $magnetPage = $this->pages->get($magnetId);
            if ($magnetPage->id) {
                $fileField = $magnetPage->get($fieldName);
                if ($fileField instanceof Pagefiles)
                    $fileField = $fileField->first();

                if ($fileField instanceof Pagefile && file_exists($fileField->filename)) {
                    $mail->attachment($fileField->filename);
                    $body = $this->download_email_body_attached ?: $this->_("Please find your requested file attached to this email.");
                    $mail->body($body);
                    $this->trackDownload($leadId);
                    $fileAttached = true;
                }
            }
        }

        if (!$fileAttached) {
            $downloadUrl = $this->generateDownloadToken($magnetId, $leadId, $fieldName);
            $body = $this->download_email_body ?: $this->_("Click here to download: {link}");
            $mail->body(str_replace('{link}', $downloadUrl, $body));
        }

        return $mail->send();
    }

    /**
     * Sends the confirmation email for DOI
     */
    protected function sendConfirmationEmail($email, $token)
    {
        $confirmUrl = $this->pages->get(1)->httpUrl . "lead-confirm/" . $token;

        $sender = $this->sender_email ?: 'noreply@yoursite.com';
        $subject = $this->confirm_email_subject ?: $this->_('Please confirm your email address');
        $body = $this->confirm_email_body ?: ($this->_("Please click the following link to confirm your email address and receive your download:") . "\n\n{link}");

        $mail = new WireMail();
        $mail->to($email);
        $mail->from($sender);
        $mail->subject($subject);
        $mail->body(str_replace('{link}', $confirmUrl, $body));

        return $mail->send();
    }

    /**
     * Handles the URL Hook for email confirmation
     * @param HookEvent $event
     */
    public function handleConfirmationRequest($event)
    {
        $token = $event->arguments('token');
        $token = $this->sanitizer->text($token);

        // Find lead by confirmation token
        $sql = "SELECT id, email, magnet_id, magnet_field_name FROM leads_archive WHERE confirmation_token=:token";
        $query = $this->database->prepare($sql);
        $query->bindValue(':token', $token);
        $query->execute();

        if ($query->rowCount() > 0) {
            $lead = $query->fetch(\PDO::FETCH_ASSOC);

            // Mark as confirmed and clear token
            $updateSql = "UPDATE leads_archive SET confirmed=1, confirmation_token=NULL WHERE id=:id";
            $updateQuery = $this->database->prepare($updateSql);
            $updateQuery->bindValue(':id', $lead['id']);
            $updateQuery->execute();

            // Send the actual download email
            $this->sendDownloadEmail($lead['email'], $lead['magnet_id'], $lead['id'], $lead['magnet_field_name']);

            // Redirect to homepage with success message
            $redirectUrl = $this->pages->get(1)->url . '?success=1';
            if ($this->doi_redirect_page) {
                $p = $this->pages->get((int) $this->doi_redirect_page);
                if ($p->id && $p->viewable()) {
                    $redirectUrl = $p->url;
                }
            }
            $this->session->redirect($redirectUrl);
        }

        throw new Wire404Exception();
    }

    /**
     * Handles the URL Hook for file download
     * @param HookEvent $event
     */
    public function handleDownloadRequest($event)
    {
        $token = $event->arguments('token');
        $token = $this->sanitizer->text($token);

        // Validate token against DB and check expiry
        $sql = "SELECT magnet_id, lead_id, magnet_field_name FROM lead_tokens WHERE token=:token AND expires_at > NOW()";
        $query = $this->database->prepare($sql);
        $query->bindValue(':token', $token);
        $query->execute();

        if ($query->rowCount() > 0) {
            $data = $query->fetch(\PDO::FETCH_ASSOC);
            $magnetId = (int) $data['magnet_id'];
            $leadId = (int) $data['lead_id'];
            $fieldName = $data['magnet_field_name'];

            $magnetPage = $this->pages->get($magnetId);

            if ($magnetPage->id && $magnetPage->viewable()) {
                $fileField = $magnetPage->get($fieldName);

                if ($fileField instanceof Pagefiles) {
                    $fileField = $fileField->first();
                }

                if ($fileField instanceof Pagefile && file_exists($fileField->filename)) {
                    // Track the download
                    $this->trackDownload($leadId);

                    wireSendFile($fileField->filename);
                    exit;
                }
            }
        }

        throw new Wire404Exception();
    }

    /**
     * Increments the download count for a specific lead
     * @param int $leadId
     */
    protected function trackDownload($leadId)
    {
        if (!$leadId)
            return;

        $sql = "UPDATE leads_archive SET download_count = download_count + 1 WHERE id = :id";
        $query = $this->database->prepare($sql);
        $query->bindValue(':id', $leadId, \PDO::PARAM_INT);
        $query->execute();
    }
}