<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to ATEA Seattle</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8fafc;
        }
        .email-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #667eea;
            font-size: 24px;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 16px;
        }
        .content {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
            color: #2d3748;
        }
        .message {
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.7;
            color: #4a5568;
        }
        .signup-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px 32px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            text-align: center;
            margin: 20px 0;
            transition: transform 0.2s;
        }
        .signup-button:hover {
            transform: translateY(-2px);
        }
        .link-info {
            background: #f7fafc;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 30px 0;
            border-radius: 4px;
        }
        .link-info h3 {
            margin: 0 0 10px 0;
            color: #2d3748;
            font-size: 16px;
        }
        .link-url {
            word-break: break-all;
            color: #667eea;
            font-size: 14px;
            background: white;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
        }
        .features {
            margin: 30px 0;
        }
        .feature {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .feature-icon {
            width: 20px;
            height: 20px;
            background: #48bb78;
            border-radius: 50%;
            margin-right: 15px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
        }
        .footer {
            background: #f7fafc;
            padding: 30px;
            text-align: center;
            font-size: 14px;
            color: #718096;
        }
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        @media (max-width: 600px) {
            body {
                padding: 10px;
            }
            .content {
                padding: 30px 20px;
            }
            .header {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo">ATEA</div>
            <h1>Welcome to ATEA Seattle</h1>
            <p>Asian Trade & Entrepreneur Association</p>
        </div>
        
        <div class="content">
            <div class="greeting">
                Hello {{ $memberName }},
            </div>
            
            <div class="message">
                <p>You've been invited to join the <strong>Asian Trade & Entrepreneur Association (ATEA) Seattle Chapter</strong>! We're excited to welcome you to our vibrant community of business professionals and entrepreneurs.</p>
                
                @if($membershipId)
                <p><strong>Your Membership ID:</strong> {{ $membershipId }}</p>
                @endif
                
                <p>To complete your membership, please click the button below to fill out your member profile:</p>
            </div>
            
            <div style="text-align: center;">
                <a href="{{ $signupLink }}" class="signup-button">
                    Complete Your Profile
                </a>
            </div>
            
            <div class="link-info">
                <h3>Can't click the button? Copy and paste this link:</h3>
                <div class="link-url">{{ $signupLink }}</div>
            </div>
            
            <div class="features">
                <h3 style="color: #2d3748; margin-bottom: 20px;">What you'll get as an ATEA member:</h3>
                
                <div class="feature">
                    <div class="feature-icon">✓</div>
                    <div>Access to exclusive networking events and business workshops</div>
                </div>
                
                <div class="feature">
                    <div class="feature-icon">✓</div>
                    <div>Connect with fellow Asian entrepreneurs and business leaders</div>
                </div>
                
                <div class="feature">
                    <div class="feature-icon">✓</div>
                    <div>Business development resources and mentorship opportunities</div>
                </div>
                
                <div class="feature">
                    <div class="feature-icon">✓</div>
                    <div>Member directory access for business partnerships</div>
                </div>
                
                <div class="feature">
                    <div class="feature-icon">✓</div>
                    <div>Community support and advocacy for Asian businesses</div>
                </div>
            </div>
            
            <div class="message">
                <p><strong>Important:</strong> This invitation link will expire in 45 days. Please complete your profile as soon as possible to ensure your membership is activated.</p>
                
                <p>If you have any questions, please don't hesitate to contact us.</p>
                
                <p>Welcome to the community!</p>
                
                <p>Best regards,<br>
                {{ $adminName }}<br>
                ATEA Seattle Chapter</p>
            </div>
        </div>
        
        <div class="footer">
            <p>
                <strong>Asian Trade & Entrepreneur Association - Seattle Chapter</strong><br>
                Building bridges, creating opportunities, fostering success
            </p>
            <p>
                <a href="mailto:info@atea-seattle.org">info@atea-seattle.org</a> | 
                <a href="https://atea-seattle.org">www.atea-seattle.org</a>
            </p>
            <p style="margin-top: 20px; font-size: 12px;">
                This email was sent because you were invited to join ATEA Seattle. 
                If you believe this was sent in error, please contact us.
            </p>
        </div>
    </div>
</body>
</html>