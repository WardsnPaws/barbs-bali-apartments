<?php
// Add PHP functionality here if needed
// require_once '../includes/core.php';
// session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Template Editor - Barbs Bali Apartments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- TinyMCE with your API key -->
    <script src="https://cdn.tiny.cloud/1/63ozzrod3ukhwtzdr7ey35thf5jpl8u0iyxlwcl9xlj6x496/tinymce/8/tinymce.min.js" referrerpolicy="origin" crossorigin="anonymous"></script>
    <style>
        :root {
            --primary-color: #d2691e;
            --secondary-color: #f4f4f4;
            --border-color: #ddd;
            --text-muted: #6c757d;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }

        .editor-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header-section {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-section h1 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.8rem;
        }

        .editor-layout {
            display: grid;
            grid-template-columns: 300px 1fr 350px;
            gap: 25px;
            min-height: 600px;
        }

        .sidebar, .preview-panel {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .sidebar {
            max-height: 80vh;
            overflow-y: auto;
        }

        .sidebar-header {
            background: var(--primary-color);
            color: white;
            padding: 15px 20px;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .variable-group {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .variable-group:last-child {
            border-bottom: none;
        }

        .variable-group h4 {
            color: var(--primary-color);
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .variable-item {
            background: var(--secondary-color);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .variable-item:hover {
            background: #e9ecef;
            border-color: var(--primary-color);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .variable-name {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: var(--primary-color);
            font-size: 0.85rem;
        }

        .variable-description {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 3px;
        }

        .main-editor {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }

        .form-control {
            border-radius: 6px;
            border: 1px solid var(--border-color);
            padding: 10px 15px;
            font-size: 0.95rem;
            transition: border-color 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(210, 105, 30, 0.25);
        }

        .preview-panel {
            max-height: 80vh;
            overflow-y: auto;
        }

        .preview-header {
            background: var(--primary-color);
            color: white;
            padding: 15px 20px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .preview-content {
            padding: 20px;
        }

        .preview-iframe {
            width: 100%;
            min-height: 500px;
            border: none;
            background: white;
        }

        .btn-group-custom {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .btn-primary-custom {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            padding: 12px 25px;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
        }

        .btn-primary-custom:hover {
            background: #b8591a;
            border-color: #b8591a;
            color: white;
            transform: translateY(-1px);
        }

        .template-selector {
            background: #f8f9fa;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .template-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .template-tab {
            background: white;
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .template-tab.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .template-tab:hover:not(.active) {
            background: #f0f0f0;
        }

        .preview-mode-selector {
            display: flex;
            gap: 5px;
        }

        .preview-mode-btn {
            background: none;
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
        }

        .preview-mode-btn.active {
            background: rgba(255,255,255,0.2);
        }

        @media (max-width: 1200px) {
            .editor-layout {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .sidebar, .preview-panel {
                max-height: none;
            }
        }
    </style>
</head>
<body>
    <div class="editor-container">
        <!-- Header -->
        <div class="header-section">
            <div>
                <h1><i class="fas fa-envelope-open-text"></i> Email Template Editor</h1>
                <small class="text-muted">Create and manage professional email templates for your booking system</small>
            </div>
            <div>
                <button class="btn btn-outline-secondary me-2" onclick="saveTemplate()">
                    <i class="fas fa-save"></i> Save Draft
                </button>
                <button class="btn btn-primary-custom" onclick="publishTemplate()">
                    <i class="fas fa-paper-plane"></i> Save & Activate
                </button>
            </div>
        </div>

        <!-- Template Selector -->
        <div class="template-selector">
            <h5 class="mb-3"><i class="fas fa-layer-group"></i> Template Type</h5>
            <div class="template-tabs">
                <div class="template-tab active" data-type="booking_confirmation">
                    <i class="fas fa-check-circle"></i> Booking Confirmation
                </div>
                <div class="template-tab" data-type="balance_reminder">
                    <i class="fas fa-credit-card"></i> Balance Reminder
                </div>
                <div class="template-tab" data-type="checkin_reminder">
                    <i class="fas fa-calendar-check"></i> Check-in Reminder
                </div>
                <div class="template-tab" data-type="custom">
                    <i class="fas fa-star"></i> Custom Template
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <label for="templateName" class="form-label">Template Name</label>
                    <input type="text" class="form-control" id="templateName" placeholder="e.g., Booking Confirmation V2">
                </div>
                <div class="col-md-6">
                    <label for="subjectLine" class="form-label">Email Subject</label>
                    <input type="text" class="form-control" id="subjectLine" placeholder="Your Booking Confirmation - {{reservationnumber}}">
                </div>
            </div>
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle"></i> 
                <strong>Pro Tip:</strong> Use the <strong>Merge Tags</strong> dropdown in the editor toolbar, or click variables from the sidebar to insert booking data into your template.
            </div>
        </div>

        <!-- Main Editor Layout -->
        <div class="editor-layout">
            <!-- Variables Sidebar -->
            <div class="sidebar">
                <div class="sidebar-header">
                    <i class="fas fa-tags"></i> Available Variables
                </div>
                
                <div class="variable-group">
                    <h4><i class="fas fa-user"></i> Guest Information</h4>
                    <div class="variable-item" onclick="insertVariable('{{guestfirstname}}')">
                        <div class="variable-name">{{guestfirstname}}</div>
                        <div class="variable-description">Guest's first name</div>
                    </div>
                    <div class="variable-item" onclick="insertVariable('{{guestlastname}}')">
                        <div class="variable-name">{{guestlastname}}</div>
                        <div class="variable-description">Guest's last name</div>
                    </div>
                    <div class="variable-item" onclick="insertVariable('{{guestemail}}')">
                        <div class="variable-name">{{guestemail}}</div>
                        <div class="variable-description">Guest's email address</div>
                    </div>
                    <div class="variable-item" onclick="insertVariable('{{guestphone}}')">
                        <div class="variable-name">{{guestphone}}</div>
                        <div class="variable-description">Guest's phone number</div>
                    </div>
                </div>

                <div class="variable-group">
                    <h4><i class="fas fa-bed"></i> Booking Details</h4>
                    <div class="variable-item" onclick="insertVariable('{{reservationnumber}}')">
                        <div class="variable-name">{{reservationnumber}}</div>
                        <div class="variable-description">Unique booking reference</div>
                    </div>
                    <div class="variable-item" onclick="insertVariable('{{apartmentnumber}}')">
                        <div class="variable-name">{{apartmentnumber}}</div>
                        <div class="variable-description">Apartment name/number</div>
                    </div>
                    <div class="variable-item" onclick="insertVariable('{{arrivaldatelong}}')">
                        <div class="variable-name">{{arrivaldatelong}}</div>
                        <div class="variable-description">Check-in date (full format)</div>
                    </div>
                    <div class="variable-item" onclick="insertVariable('{{departuredatelong}}')">
                        <div class="variable-name">{{departuredatelong}}</div>
                        <div class="variable-description">Check-out date (full format)</div>
                    </div>
                    <div class="variable-item" onclick="insertVariable('{{numberofnights}}')">
                        <div class="variable-name">{{numberofnights}}</div>
                        <div class="variable-description">Total nights booked</div>
                    </div>
                </div>

                <div class="variable-group">
                    <h4><i class="fas fa-dollar-sign"></i> Financial</h4>
                    <div class="variable-item" onclick="insertVariable('{{accommodationtotal}}')">
                        <div class="variable-name">{{accommodationtotal}}</div>
                        <div class="variable-description">Accommodation cost</div>
                    </div>
                    <div class="variable-item" onclick="insertVariable('{{extrastotal}}')">
                        <div class="variable-name">{{extrastotal}}</div>
                        <div class="variable-description">Total extras cost</div>
                    </div>
                    <div class="variable-item" onclick="insertVariable('{{grandtotal}}')">
                        <div class="variable-name">{{grandtotal}}</div>
                        <div class="variable-description">Total booking cost</div>
                    </div>
                    <div class="variable-item" onclick="insertVariable('{{amountpaid}}')">
                        <div class="variable-name">{{amountpaid}}</div>
                        <div class="variable-description">Amount already paid</div>
                    </div>
                    <div class="variable-item" onclick="insertVariable('{{balancedue}}')">
                        <div class="variable-name">{{balancedue}}</div>
                        <div class="variable-description">Outstanding balance</div>
                    </div>
                </div>

                <div class="variable-group">
                    <h4><i class="fas fa-plus-circle"></i> Extras & Services</h4>
                    <div class="variable-item" onclick="insertVariable('{{includedextras}}')">
                        <div class="variable-name">{{includedextras}}</div>
                        <div class="variable-description">List of booked extras</div>
                    </div>
                    <div class="variable-item" onclick="insertVariable('{{sofabedrow}}')">
                        <div class="variable-name">{{sofabedrow}}</div>
                        <div class="variable-description">Sofa bed information</div>
                    </div>
                    <div class="variable-item" onclick="insertVariable('{{paymentlink}}')">
                        <div class="variable-name">{{paymentlink}}</div>
                        <div class="variable-description">Secure payment link</div>
                    </div>
                </div>

                <div class="variable-group">
                    <h4><i class="fas fa-info-circle"></i> System Info</h4>
                    <div class="variable-item" onclick="insertVariable('{{currentdate}}')">
                        <div class="variable-name">{{currentdate}}</div>
                        <div class="variable-description">Current date</div>
                    </div>
                    <div class="variable-item" onclick="insertVariable('{{websiteurl}}')">
                        <div class="variable-name">{{websiteurl}}</div>
                        <div class="variable-description">Website URL</div>
                    </div>
                </div>
            </div>

            <!-- Main Editor -->
            <div class="main-editor">
                <div class="form-group">
                    <label for="emailContent">
                        <i class="fas fa-edit"></i> Email Content
                        <small class="text-muted">(Click variables from the sidebar to insert them)</small>
                    </label>
                    <textarea id="emailContent" name="emailContent" style="height: 400px;">
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            color: #333; 
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }
        .container { 
            max-width: 600px;
            margin: 0 auto;
            padding: 20px; 
        }
        .header {
            background: #d2691e;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background: white;
            padding: 30px;
            border: 1px solid #ddd;
        }
        .highlight { 
            background-color: #fff8e1; 
            padding: 20px; 
            border-radius: 8px; 
            margin: 20px 0; 
            border-left: 4px solid #d2691e; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 15px; 
        }
        td { 
            padding: 8px 0; 
            border-bottom: 1px solid #f0f0f0;
        }
        .btn { 
            display: inline-block; 
            background-color: #d2691e; 
            color: white; 
            padding: 15px 30px; 
            text-decoration: none; 
            border-radius: 8px; 
            margin: 15px 0; 
            font-weight: bold; 
            text-align: center;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 14px;
            color: #666;
            border-radius: 0 0 8px 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏨 Barbs Bali Apartments</h1>
            <p>Your Booking Confirmation</p>
        </div>
        
        <div class="content">
            <h2>Hello {{guestfirstname}} {{guestlastname}}!</h2>
            
            <p>Thank you for choosing Barbs Bali Apartments. We're excited to welcome you to paradise!</p>
            
            <div class="highlight">
                <h3>📋 Your Booking Details</h3>
                <table>
                    <tr>
                        <td><strong>Reservation Number:</strong></td>
                        <td>{{reservationnumber}}</td>
                    </tr>
                    <tr>
                        <td><strong>Apartment:</strong></td>
                        <td>{{apartmentnumber}}</td>
                    </tr>
                    <tr>
                        <td><strong>Check-in:</strong></td>
                        <td>{{arrivaldatelong}}</td>
                    </tr>
                    <tr>
                        <td><strong>Check-out:</strong></td>
                        <td>{{departuredatelong}}</td>
                    </tr>
                    <tr>
                        <td><strong>Nights:</strong></td>
                        <td>{{numberofnights}}</td>
                    </tr>
                </table>
                {{sofabedrow}}
            </div>
            
            <h3>💰 Payment Summary</h3>
            <table>
                <tr>
                    <td><strong>Accommodation Total:</strong></td>
                    <td>${{accommodationtotal}} AUD</td>
                </tr>
                <tr>
                    <td><strong>Extras Total:</strong></td>
                    <td>${{extrastotal}} AUD</td>
                </tr>
                <tr style="border-top: 2px solid #d2691e; font-weight: bold;">
                    <td><strong>Grand Total:</strong></td>
                    <td><strong>${{grandtotal}} AUD</strong></td>
                </tr>
            </table>
            
            <h3>🎯 Included Extras</h3>
            {{includedextras}}
            
            <p><strong>We can't wait to see you soon!</strong></p>
            <p>If you have any questions, please don't hesitate to contact us.</p>
        </div>
        
        <div class="footer">
            <p><strong>Barbs Bali Apartments</strong><br>
            📧 Email: info@barbsbaliapartments.com<br>
            🌐 Website: {{websiteurl}}<br>
            📅 Booking Date: {{currentdate}}</p>
        </div>
    </div>
</body>
</html>
                    </textarea>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="isActive" checked>
                            <label class="form-check-label" for="isActive">
                                <span class="status-indicator status-active"></span>
                                Template is Active
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="trackOpens">
                            <label class="form-check-label" for="trackOpens">
                                Enable Email Tracking
                            </label>
                        </div>
                    </div>
                </div>

                <div class="btn-group-custom">
                    <button class="btn btn-outline-secondary" onclick="loadTemplate()">
                        <i class="fas fa-folder-open"></i> Load Existing
                    </button>
                    <button class="btn btn-outline-primary" onclick="testEmail()">
                        <i class="fas fa-paper-plane"></i> Send Test Email
                    </button>
                </div>
            </div>

            <!-- Live Preview Panel -->
            <div class="preview-panel">
                <div class="preview-header">
                    <span><i class="fas fa-eye"></i> Live Preview</span>
                    <div class="preview-mode-selector">
                        <button class="preview-mode-btn active" onclick="setPreviewMode('desktop')">
                            <i class="fas fa-desktop"></i>
                        </button>
                        <button class="preview-mode-btn" onclick="setPreviewMode('mobile')">
                            <i class="fas fa-mobile-alt"></i>
                        </button>
                    </div>
                </div>
                <div class="preview-content">
                    <iframe id="previewFrame" class="preview-iframe" src="about:blank"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let tinymceEditor = null;
        let currentTemplate = 'booking_confirmation';
        let previewMode = 'desktop';

        // Sample data for preview
        const sampleData = {
            guestfirstname: 'Sarah',
            guestlastname: 'Johnson',
            guestemail: 'sarah.johnson@email.com',
            guestphone: '+61 412 345 678',
            reservationnumber: 'BB-2024-001234',
            apartmentnumber: 'Apartment 1 - Garden View',
            arrivaldatelong: 'Monday, December 23rd, 2024',
            departuredatelong: 'Friday, December 27th, 2024',
            numberofnights: '4',
            accommodationtotal: '480.00',
            extrastotal: '85.00',
            grandtotal: '565.00',
            amountpaid: '113.00',
            balancedue: '452.00',
            includedextras: '<ul><li>Airport Pickup - $35.00 AUD (one-time)</li><li>Late Checkout - $40.00 AUD (one-time)</li><li>Daily Linen Change - $10.00 AUD ($2.50 × 4 nights)</li></ul>',
            sofabedrow: '<tr><td><strong>Sofa Bed:</strong></td><td>✅ Included ($20.00 total)</td></tr>',
            paymentlink: 'https://barbsbaliapartments.com/pay-balance.php?ref=BB-2024-001234',
            currentdate: new Date().toLocaleDateString('en-AU', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            }),
            websiteurl: 'https://barbsbaliapartments.com'
        };

        // Initialize TinyMCE with your API key and premium features
        tinymce.init({
            selector: '#emailContent',
            height: 400,
            plugins: [
                // Core editing features
                'anchor', 'autolink', 'charmap', 'codesample', 'emoticons', 'link', 'lists', 'media', 
                'searchreplace', 'table', 'visualblocks', 'wordcount', 'code', 'preview', 'fullscreen',
                // Premium features
                'checklist', 'mediaembed', 'casechange', 'formatpainter', 'pageembed', 'a11ychecker', 
                'tinymcespellchecker', 'permanentpen', 'powerpaste', 'advtable', 'advcode', 'advtemplate', 
                'mentions', 'tableofcontents', 'footnotes', 'mergetags', 'autocorrect', 'typography', 
                'inlinecss', 'markdown', 'importword', 'exportword', 'exportpdf'
            ],
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | ' +
                'link media table mergetags | spellcheckdialog a11ycheck typography | ' +
                'align lineheight | checklist numlist bullist indent outdent | emoticons charmap | ' +
                'code preview fullscreen | removeformat',
            content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; }',
            
            // Enhanced merge tags for your booking system
            mergetags_list: [
                { value: 'guestfirstname', title: 'Guest First Name' },
                { value: 'guestlastname', title: 'Guest Last Name' },
                { value: 'guestemail', title: 'Guest Email' },
                { value: 'guestphone', title: 'Guest Phone' },
                { value: 'reservationnumber', title: 'Reservation Number' },
                { value: 'apartmentnumber', title: 'Apartment Number' },
                { value: 'arrivaldatelong', title: 'Check-in Date (Long)' },
                { value: 'departuredatelong', title: 'Check-out Date (Long)' },
                { value: 'numberofnights', title: 'Number of Nights' },
                { value: 'accommodationtotal', title: 'Accommodation Total' },
                { value: 'extrastotal', title: 'Extras Total' },
                { value: 'grandtotal', title: 'Grand Total' },
                { value: 'amountpaid', title: 'Amount Paid' },
                { value: 'balancedue', title: 'Balance Due' },
                { value: 'includedextras', title: 'Included Extras List' },
                { value: 'sofabedrow', title: 'Sofa Bed Row' },
                { value: 'paymentlink', title: 'Payment Link' },
                { value: 'currentdate', title: 'Current Date' },
                { value: 'websiteurl', title: 'Website URL' }
            ],
            
            tinycomments_mode: 'embedded',
            tinycomments_author: 'Email Template Editor',
            a11ychecker_level: 'aa',
            
            setup: function(editor) {
                tinymceEditor = editor;
                editor.on('change keyup', function() {
                    updatePreview();
                });
            },
            
            init_instance_callback: function(editor) {
                updatePreview();
            }
        });

        // Template tab switching
        document.querySelectorAll('.template-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.template-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                currentTemplate = this.dataset.type;
                loadTemplateDefaults();
            });
        });

        // Insert variable function
        function insertVariable(variable) {
            if (tinymceEditor) {
                tinymceEditor.insertContent(variable);
                updatePreview();
            }
        }

        // Update live preview
        function updatePreview() {
            if (!tinymceEditor) return;

            let content = tinymceEditor.getContent();
            
            // Replace variables with sample data
            Object.keys(sampleData).forEach(key => {
                const regex = new RegExp(`{{${key}}}`, 'g');
                content = content.replace(regex, sampleData[key]);
            });

            // Style for mobile/desktop preview
            const previewStyle = previewMode === 'mobile' 
                ? '<style>body{max-width:320px;margin:0 auto;font-size:14px;}</style>'
                : '<style>body{max-width:600px;margin:0 auto;}</style>';

            // Update preview iframe
            const iframe = document.getElementById('previewFrame');
            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
            iframeDoc.open();
            iframeDoc.write(previewStyle + content);
            iframeDoc.close();
        }

        // Set preview mode (desktop/mobile)
        function setPreviewMode(mode) {
            previewMode = mode;
            document.querySelectorAll('.preview-mode-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelector(`[onclick="setPreviewMode('${mode}')"]`).classList.add('active');
            updatePreview();
        }

        // Load template defaults based on type
        function loadTemplateDefaults() {
            const templates = {
                booking_confirmation: {
                    name: 'Booking Confirmation Email',
                    subject: 'Your Booking is Confirmed - {{reservationnumber}}',
                    content: document.getElementById('emailContent').value
                },
                balance_reminder: {
                    name: 'Balance Payment Reminder',
                    subject: 'Payment Reminder - {{reservationnumber}} Balance Due',
                    content: `<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #d2691e; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: white; padding: 30px; border: 1px solid #ddd; }
        .highlight { background-color: #fff8e1; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #d2691e; }
        .urgent { background-color: #ffebee; border-left: 4px solid #f44336; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        td { padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .btn { display: inline-block; background-color: #d2691e; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 15px 0; font-weight: bold; text-align: center; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #666; border-radius: 0 0 8px 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💳 Payment Reminder</h1>
            <p>Balance Due for Your Upcoming Stay</p>
        </div>
        <div class="content">
            <h2>Hi {{guestfirstname}} {{guestlastname}},</h2>
            <p>This is a friendly reminder that the remaining balance for your upcoming stay is due.</p>
            <div class="highlight">
                <h3>📋 Your Booking Details</h3>
                <table>
                    <tr><td><strong>Reservation Number:</strong></td><td>{{reservationnumber}}</td></tr>
                    <tr><td><strong>Apartment:</strong></td><td>{{apartmentnumber}}</td></tr>
                    <tr><td><strong>Check-in:</strong></td><td>{{arrivaldatelong}}</td></tr>
                    <tr><td><strong>Check-out:</strong></td><td>{{departuredatelong}}</td></tr>
                </table>
            </div>
            <div class="highlight urgent">
                <h3>💰 Payment Summary</h3>
                <table>
                    <tr><td><strong>Grand Total:</strong></td><td>$\{{grandtotal}} AUD</td></tr>
                    <tr><td><strong>Amount Paid:</strong></td><td>$\{{amountpaid}} AUD</td></tr>
                    <tr style="border-top: 2px solid #f44336; font-weight: bold; color: #f44336;"><td><strong>Balance Due:</strong></td><td><strong>$\{{balancedue}} AUD</strong></td></tr>
                </table>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="{{paymentlink}}" class="btn">💳 Pay Balance Now</a>
                </div>
            </div>
            <p>Please complete your payment at your earliest convenience to ensure your reservation remains confirmed.</p>
        </div>
        <div class="footer">
            <p><strong>Barbs Bali Apartments</strong><br>Email: info@barbsbaliapartments.com<br>Website: {{websiteurl}}</p>
        </div>
    </div>
</body>
</html>`
                },
                checkin_reminder: {
                    name: 'Check-in Instructions',
                    subject: 'Check-in Tomorrow - {{reservationnumber}} Instructions',
                    content: `<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #d2691e; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: white; padding: 30px; border: 1px solid #ddd; }
        .highlight { background-color: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        td { padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .info-box { background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 15px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #666; border-radius: 0 0 8px 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🗝️ Check-in Instructions</h1>
            <p>Your Stay Begins Tomorrow!</p>
        </div>
        <div class="content">
            <h2>Welcome {{guestfirstname}} {{guestlastname}}!</h2>
            <p>We're excited to welcome you tomorrow! Here are your check-in instructions:</p>
            <div class="highlight">
                <h3>📍 Check-in Details</h3>
                <table>
                    <tr><td><strong>Reservation:</strong></td><td>{{reservationnumber}}</td></tr>
                    <tr><td><strong>Apartment:</strong></td><td>{{apartmentnumber}}</td></tr>
                    <tr><td><strong>Check-in Date:</strong></td><td>{{arrivaldatelong}}</td></tr>
                    <tr><td><strong>Check-in Time:</strong></td><td>3:00 PM onwards</td></tr>
                </table>
            </div>
            <div class="info-box">
                <h4>🏠 Property Address:</h4>
                <p><strong>Barbs Bali Apartments</strong><br>
                123 Paradise Street<br>
                Seminyak, Bali 80361<br>
                Indonesia</p>
            </div>
            <h3>🎯 Important Reminders:</h3>
            <ul>
                <li>Please bring valid ID for all guests</li>
                <li>Check-in is from 3:00 PM</li>
                <li>Late check-in after 8:00 PM requires advance notice</li>
                <li>Pool hours: 6:00 AM - 10:00 PM daily</li>
            </ul>
            <p><strong>Have a wonderful stay with us!</strong></p>
        </div>
        <div class="footer">
            <p><strong>Barbs Bali Apartments</strong><br>Email: info@barbsbaliapartments.com<br>Website: {{websiteurl}}</p>
        </div>
    </div>
</body>
</html>`
                },
                custom: {
                    name: 'Custom Email Template',
                    subject: 'Custom Email - {{reservationnumber}}',
                    content: `<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #d2691e; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: white; padding: 30px; border: 1px solid #ddd; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #666; border-radius: 0 0 8px 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏨 Barbs Bali Apartments</h1>
            <p>Custom Email Template</p>
        </div>
        <div class="content">
            <h2>Hello {{guestfirstname}} {{guestlastname}}!</h2>
            <p>This is a custom email template. You can customize this content for your specific needs.</p>
            <p><strong>Reservation:</strong> {{reservationnumber}}</p>
            <p>Add your custom content here...</p>
        </div>
        <div class="footer">
            <p><strong>Barbs Bali Apartments</strong><br>Email: info@barbsbaliapartments.com<br>Website: {{websiteurl}}</p>
        </div>
    </div>
</body>
</html>`
                }
            };

            const template = templates[currentTemplate];
            if (template) {
                document.getElementById('templateName').value = template.name;
                document.getElementById('subjectLine').value = template.subject;
                if (tinymceEditor && currentTemplate !== 'booking_confirmation') {
                    tinymceEditor.setContent(template.content);
                    updatePreview();
                }
            }
        }

        // Save template function
        function saveTemplate() {
            const templateData = {
                name: document.getElementById('templateName').value,
                type: currentTemplate,
                subject: document.getElementById('subjectLine').value,
                content: tinymceEditor ? tinymceEditor.getContent() : '',
                active: document.getElementById('isActive').checked,
                tracking: document.getElementById('trackOpens').checked
            };

            console.log('Saving template:', templateData);
            showNotification('Template saved successfully!', 'success');
        }

        // Publish template function
        function publishTemplate() {
            saveTemplate();
            showNotification('Template published and activated!', 'success');
        }

        // Test email function
        function testEmail() {
            const testEmail = prompt('Enter test email address:', 'test@example.com');
            if (testEmail) {
                console.log('Sending test email to:', testEmail);
                showNotification(`Test email sent to ${testEmail}!`, 'info');
            }
        }

        // Load existing template function
        function loadTemplate() {
            console.log('Loading existing template...');
            showNotification('Template loaded successfully!', 'info');
        }

        // Show notification function
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 1050; max-width: 300px;';
            notification.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
                </div>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadTemplateDefaults();
        });
    </script>
</body>
</html>