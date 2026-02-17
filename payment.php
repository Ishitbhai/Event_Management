<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Event Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .payment-wrapper {
            width: 100%;
            max-width: 1200px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(10px);
        }
        
        .event-section {
            border-right: 1px solid #e5e7eb;
            padding-right: 30px;
        }
        
        .payment-section {
            padding-left: 30px;
        }
        
        .section-title {
            font-size: 28px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
        }
        
        .section-title::after {
            content: '';
            flex: 1;
            height: 2px;
            background: linear-gradient(90deg, #667eea, transparent);
            margin-left: 20px;
        }
        
        .section-title i {
            color: #667eea;
            font-size: 24px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .event-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 30px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 
                0 10px 40px rgba(102, 126, 234, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.1) inset;
        }
        
        .event-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: shimmer 3s ease-in-out infinite;
        }
        
        .event-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 100px;
            background: linear-gradient(180deg, transparent, rgba(0,0,0,0.1));
        }
        
        @keyframes shimmer {
            0%, 100% { transform: rotate(0deg); }
            50% { transform: rotate(180deg); }
        }
        
        .event-header {
            position: relative;
            z-index: 1;
        }
        
        .event-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .event-details {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .event-detail-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 16px;
        }
        
        .event-detail-item i {
            width: 20px;
            text-align: center;
            opacity: 0.9;
        }
        
        .event-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 20px;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .payment-method {
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            padding: 24px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }
        
        .payment-method::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
            z-index: 0;
        }
        
        .payment-method::after {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(135deg, #667eea, #764ba2, #f093fb);
            border-radius: 16px;
            opacity: 0;
            transition: opacity 0.4s ease;
            z-index: -1;
        }
        
        .payment-method:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 12px 24px rgba(102, 126, 234, 0.15);
        }
        
        .payment-method.selected {
            border-color: #667eea;
            color: white;
            transform: translateY(-2px) scale(1.02);
        }
        
        .payment-method.selected::before {
            opacity: 1;
        }
        
        .payment-method.selected::after {
            opacity: 1;
        }
        
        .payment-method-content {
            position: relative;
            z-index: 1;
        }
        
        .payment-method i {
            font-size: 36px;
            margin-bottom: 12px;
            display: block;
            transition: transform 0.3s ease;
        }
        
        .payment-method:hover i {
            transform: scale(1.1);
        }
        
        .payment-method span {
            font-weight: 600;
            font-size: 15px;
            letter-spacing: 0.5px;
        }
        
        .payment-details {
            background: #f9fafb;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            min-height: 200px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 16px 18px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            background: white;
            position: relative;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 
                0 0 0 4px rgba(102, 126, 234, 0.1),
                0 4px 12px rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }
        
        .form-group input::placeholder {
            color: #9ca3af;
            font-weight: 400;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .price-section {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 25px;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 
                0 4px 12px rgba(0, 0, 0, 0.05),
                0 0 0 1px rgba(255, 255, 255, 0.5) inset;
        }
        
        .price-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f093fb);
            background-size: 200% 100%;
            animation: gradientMove 3s ease-in-out infinite;
        }
        
        .price-label {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 8px;
            font-weight: 500;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        .price-amount {
            font-size: 36px;
            font-weight: 800;
            color: #1e293b;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
        }
        
        .pay-button {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 16px;
            padding: 20px;
            font-size: 18px;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .pay-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s ease;
        }
        
        .pay-button::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, #667eea, #764ba2, #f093fb);
            opacity: 0;
            transition: opacity 0.4s ease;
            border-radius: 16px;
        }
        
        .pay-button:hover::before {
            left: 100%;
        }
        
        .pay-button:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 16px 32px rgba(102, 126, 234, 0.4);
        }
        
        .pay-button:active {
            transform: translateY(-1px) scale(1.01);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-top: 20px;
            transition: color 0.3s ease;
        }
        
        .back-link:hover {
            color: #764ba2;
        }
        
        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 24px;
            color: #64748b;
            font-size: 13px;
            font-weight: 500;
            padding: 12px 20px;
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .security-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.1);
        }
        
        .security-badge i {
            color: #10b981;
            font-size: 16px;
        }
        
        .verify-button {
            width: 100%;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 16px;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .verify-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .verify-button:hover::before {
            left: 100%;
        }
        
        .verify-button:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }
        
        .verify-button:disabled {
            background: linear-gradient(135deg, #9ca3af, #6b7280);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .verify-button:disabled::before {
            display: none;
        }
        
        .upi-verified {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 18px;
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            border: 2px solid #10b981;
            border-radius: 12px;
            color: #065f46;
            position: relative;
            overflow: hidden;
        }
        
        .upi-verified::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #10b981, #059669);
        }
        
        .upi-verified i {
            color: #10b981;
            font-size: 20px;
        }
        
        .upi-loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f4f6;
            border-radius: 50%;
            border-top-color: #10b981;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        #payment-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }
        
        .payment-modal {
            background: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: scale(0.8) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        .payment-modal-content h3 {
            color: #1f2937;
            font-size: 24px;
            font-weight: 700;
            margin: 20px 0 10px;
        }
        
        .payment-modal-content p {
            color: #6b7280;
            font-size: 16px;
            margin: 0;
        }
        
        .payment-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        .payment-icon.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        
        .payment-icon i {
            font-size: 40px;
            color: white;
        }
        
        .payment-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
        }
        
        .payment-loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
            margin-right: 8px;
        }
        

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 9998;
            animation: slideInRight 0.3s ease-out;
            min-width: 300px;
        }
        
        .notification.error {
            border-left: 4px solid #ef4444;
        }
        
        .notification.success {
            border-left: 4px solid #10b981;
        }
        
        .notification i {
            font-size: 20px;
        }
        
        .notification.error i {
            color: #ef4444;
        }
        
        .notification.success i {
            color: #10b981;
        }
        
        .notification span {
            color: #1f2937;
            font-weight: 500;
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @media (max-width: 768px) {
            .payment-wrapper {
                grid-template-columns: 1fr;
                padding: 20px;
                gap: 20px;
            }
            
            .event-section, .payment-section {
                border: none;
                padding: 0;
            }
            
            .payment-methods {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="payment-wrapper">

    <div class="event-section">
        <h2 class="section-title">
            <i class="fas fa-calendar-check"></i>
            Event Details
        </h2>
        
        <div class="event-card">
            <div class="event-header">
                <h3 class="event-title">Tech Conference 2024</h3>
                
                <div class="event-details">
                    <div class="event-detail-item">
                        <i class="fas fa-tag"></i>
                        <span>Technology</span>
                    </div>
                    <div class="event-detail-item">
                        <i class="fas fa-clock"></i>
                        <span>25 Dec 2024, 10:00 AM</span>
                    </div>
                    <div class="event-detail-item">
                        <i class="fas fa-clock"></i>
                        <span>25 Dec 2024, 06:00 PM</span>
                    </div>
                    <div class="event-detail-item">
                        <i class="fas fa-users"></i>
                        <span>100 Seats Available</span>
                    </div>
                    <div class="event-detail-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Convention Center</span>
                    </div>
                </div>
                
                <div class="event-badge">
                    <i class="fas fa-star"></i> Premium Event
                </div>
            </div>
        </div>
        
        <div class="security-badge">
            <i class="fas fa-shield-alt"></i>
            <span>Secure Payment Powered by SSL</span>
        </div>
    </div>
    
  
    <div class="payment-section">
        <h2 class="section-title">
            <i class="fas fa-credit-card"></i>
            Payment Information
        </h2>
        
        <div class="price-section">
            <div class="price-label">Total Amount</div>
            <div class="price-amount">₹500</div>
        </div>
        
        <form method="POST" action="process_payment.php">
            <input type="hidden" name="event_id" value="1">
            <input type="hidden" name="payment_method" id="payment_method" required>
            
          
            <div class="payment-methods">
                <div class="payment-method" onclick="selectPaymentMethod(this, 'credit_card')">
                    <div class="payment-method-content">
                        <i class="fas fa-credit-card"></i>
                        <span>Credit Card</span>
                    </div>
                </div>
                <div class="payment-method" onclick="selectPaymentMethod(this, 'debit_card')">
                    <div class="payment-method-content">
                        <i class="fas fa-credit-card"></i>
                        <span>Debit Card</span>
                    </div>
                </div>
                <div class="payment-method" onclick="selectPaymentMethod(this, 'upi')">
                    <div class="payment-method-content">
                        <i class="fas fa-mobile-alt"></i>
                        <span>UPI</span>
                    </div>
                </div>
                <div class="payment-method" onclick="selectPaymentMethod(this, 'net_banking')">
                    <div class="payment-method-content">
                        <i class="fas fa-university"></i>
                        <span>Net Banking</span>
                    </div>
                </div>
            </div>
            
     
            <div class="payment-details">
               
                <div id="card_details" style="display: none;">
                    <div class="form-group">
                        <label>Card Number</label>
                        <input type="text" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19">
                    </div>
                    
                    <div class="form-group">
                        <label>Cardholder Name</label>
                        <input type="text" name="cardholder_name" placeholder="John Doe">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Expiry Date</label>
                            <input type="text" name="expiry_date" placeholder="MM/YY" maxlength="5">
                        </div>
                        
                        <div class="form-group">
                            <label>CVV</label>
                            <input type="text" name="cvv" placeholder="123" maxlength="3">
                        </div>
                    </div>
                </div>
                
                     <div id="upi_details" style="display: none;">
                    <div class="form-group">
                        <label>UPI ID</label>
                        <input type="text" name="upi_id" id="upi_id" placeholder="yourname@upi" onblur="validateUPI()">
                        <small id="upi_error" style="color: #ef4444; display: none; font-size: 12px; margin-top: 5px;">Please enter a valid UPI ID</small>
                    </div>
                    
                    <div class="form-group" id="upi_verify_section" style="display: none;">
                        <button type="button" class="verify-button" onclick="verifyUPI()">
                            <i class="fas fa-check-circle"></i> Verify UPI ID
                        </button>
                        <div id="upi_verify_result" style="margin-top: 10px; font-size: 14px;"></div>
                    </div>
                    
                    <div class="form-group" id="upi_verified_section" style="display: none;">
                        <div class="upi-verified">
                            <i class="fas fa-check-circle" style="color: #10b981;"></i>
                            <span>UPI ID Verified Successfully</span>
                            <div id="verified_upi_name" style="font-weight: 600; color: #1f2937; margin-top: 5px;"></div>
                        </div>
                    </div>
                </div>
                
                
                <div id="net_banking_details" style="display: none;">
                    <div class="form-group">
                        <label>Select Bank</label>
                        <select name="bank_name">
                            <option value="">Select your bank</option>
                            <option value="sbi">State Bank of India</option>
                            <option value="hdfc">HDFC Bank</option>
                            <option value="icici">ICICI Bank</option>
                            <option value="pnb">Punjab National Bank</option>
                            <option value="axis">Axis Bank</option>
                            <option value="kotak">Kotak Mahindra Bank</option>
                        </select>
                    </div>
                </div>
                

                <div id="default_message" style="text-align: center; color: #6b7280; padding: 40px;">
                    <i class="fas fa-hand-pointer" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                    <p>Select a payment method to continue</p>
                </div>
            </div>
            
            <button type="submit" class="pay-button" onclick="processPayment(event)">
                <i class="fas fa-lock"></i> Proceed to Pay ₹500
            </button>
        </form>
        
        <a href="create_event.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Back to Event Creation
        </a>
    </div>
</div>

<script>
function selectPaymentMethod(element, method) {
    document.querySelectorAll('.payment-method').forEach(el => {
        el.classList.remove('selected');
    });

    element.classList.add('selected');
    

    document.getElementById('payment_method').value = method;

    document.getElementById('card_details').style.display = 'none';
    document.getElementById('upi_details').style.display = 'none';
    document.getElementById('net_banking_details').style.display = 'none';
    document.getElementById('default_message').style.display = 'none';
    
    if (method === 'credit_card' || method === 'debit_card') {
        document.getElementById('card_details').style.display = 'block';
    } else if (method === 'upi') {
        document.getElementById('upi_details').style.display = 'block';
    } else if (method === 'net_banking') {
        document.getElementById('net_banking_details').style.display = 'block';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const cardNumberInput = document.querySelector('input[name="card_number"]');
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formattedValue;
        });
    }

    const expiryInput = document.querySelector('input[name="expiry_date"]');
    if (expiryInput) {
        expiryInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.slice(0, 2) + '/' + value.slice(2, 4);
            }
            e.target.value = value;
        });
    }

    const cvvInput = document.querySelector('input[name="cvv"]');
    if (cvvInput) {
        cvvInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
    }
});

function validateUPI() {
    const upiId = document.getElementById('upi_id').value.trim();
    const upiError = document.getElementById('upi_error');
    const upiVerifySection = document.getElementById('upi_verify_section');
    const upiVerifiedSection = document.getElementById('upi_verified_section');
    
    upiError.style.display = 'none';
    upiVerifySection.style.display = 'none';
    upiVerifiedSection.style.display = 'none';
    
    const upiRegex = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+$/;
    
    if (upiId === '') {
        return false;
    }
    
    if (!upiRegex.test(upiId)) {
        upiError.style.display = 'block';
        return false;
    }
    
    upiVerifySection.style.display = 'block';
    return true;
}

function verifyUPI() {
    const upiId = document.getElementById('upi_id').value.trim();
    const verifyButton = document.querySelector('.verify-button');
    const verifyResult = document.getElementById('upi_verify_result');
    const upiVerifySection = document.getElementById('upi_verify_section');
    const upiVerifiedSection = document.getElementById('upi_verified_section');
    const verifiedUpiName = document.getElementById('verified_upi_name');
    
    verifyButton.disabled = true;
    verifyButton.innerHTML = '<div class="upi-loading"></div> Verifying...';
    verifyResult.innerHTML = '';

    setTimeout(() => {
        const isValidUPI = Math.random() > 0.2; 
        
        if (isValidUPI) {
         
            verifyResult.innerHTML = '<div style="color: #10b981;"><i class="fas fa-check-circle"></i> UPI ID verified successfully!</div>';
            
           
            const mockNames = ['Rahul Sharma', 'Priya Patel', 'Amit Kumar', 'Sneha Reddy', 'Vikram Singh'];
            const randomName = mockNames[Math.floor(Math.random() * mockNames.length)];
            verifiedUpiName.textContent = randomName;
            
            setTimeout(() => {
                upiVerifySection.style.display = 'none';
                upiVerifiedSection.style.display = 'block';
            }, 1000);
        } else {
            
            verifyResult.innerHTML = '<div style="color: #ef4444;"><i class="fas fa-exclamation-circle"></i> UPI ID not found. Please check and try again.</div>';
            verifyButton.disabled = false;
            verifyButton.innerHTML = '<i class="fas fa-check-circle"></i> Verify UPI ID';
        }
    }, 2000); 
}


function processPayment(event) {
    event.preventDefault();
    
    const payButton = document.querySelector('.pay-button');
    const paymentMethod = document.getElementById('payment_method').value;
    
    if (!paymentMethod) {
        showNotification('Please select a payment method', 'error');
        return;
    }
    
    if (paymentMethod === 'upi') {
        const upiVerifiedSection = document.getElementById('upi_verified_section');
        if (upiVerifiedSection.style.display === 'none') {
            showNotification('Please verify your UPI ID first', 'error');
            return;
        }
    }
    
    payButton.disabled = true;
    payButton.innerHTML = '<div class="payment-loading"></div> Processing Payment...';
    
    showPaymentOverlay();
    
    setTimeout(() => {
        updatePaymentOverlay('Payment Successful!', 'success');
        
        setTimeout(() => {
            window.location.href = 'index.php';
        }, 2000);
    }, 3000);
}

function showPaymentOverlay() {
    const overlay = document.createElement('div');
    overlay.id = 'payment-overlay';
    overlay.innerHTML = `
        <div class="payment-modal">
            <div class="payment-modal-content">
                <div class="payment-icon">
                    <div class="payment-spinner"></div>
                </div>
                <h3>Processing Payment</h3>
                <p>Please wait while we process your payment...</p>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);
}

function updatePaymentOverlay(message, type) {
    const overlay = document.getElementById('payment-overlay');
    const modalContent = overlay.querySelector('.payment-modal-content');
    
    if (type === 'success') {
        modalContent.innerHTML = `
            <div class="payment-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3>${message}</h3>
            <p>Redirecting to home page...</p>
        `;
    }
}

function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas ${type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'}"></i>
        <span>${message}</span>
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}
</script>

</body>
</html>
