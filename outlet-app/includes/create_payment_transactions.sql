-- =====================================================
-- Flutterwave Payment Transactions Table
-- =====================================================
-- This table stores all payment transactions processed through Flutterwave
-- for parcel delivery payments in Zambia (Mobile Money and Card payments)
-- =====================================================

CREATE TABLE IF NOT EXISTS public.payment_transactions (
    -- Primary Identifier
    id UUID NOT NULL DEFAULT gen_random_uuid(),
    
    -- Transaction References
    tx_ref TEXT NOT NULL UNIQUE, -- Our internal transaction reference (PARCEL-timestamp-unique)
    flutterwave_tx_id TEXT UNIQUE, -- Flutterwave transaction ID (after successful payment)
    flutterwave_tx_ref TEXT, -- Flutterwave transaction reference
    
    -- Related Entities
    parcel_id UUID, -- Link to parcels table
    company_id UUID NOT NULL,
    outlet_id UUID, -- Outlet where payment was initiated
    user_id UUID NOT NULL, -- User who initiated the payment
    
    -- Payment Details
    amount NUMERIC(10,2) NOT NULL CHECK (amount > 0),
    transaction_fee NUMERIC(10,2) DEFAULT 0 CHECK (transaction_fee >= 0),
    commission_percentage NUMERIC(5,2) DEFAULT 0 CHECK (commission_percentage >= 0 AND commission_percentage <= 100),
    commission_amount NUMERIC(10,2) DEFAULT 0 CHECK (commission_amount >= 0),
    net_amount NUMERIC(10,2) DEFAULT 0 CHECK (net_amount >= 0), -- amount - commission_amount
    total_amount NUMERIC(10,2) NOT NULL CHECK (total_amount > 0), -- amount + transaction_fee
    currency TEXT NOT NULL DEFAULT 'ZMW',
    exchange_rate NUMERIC(10,4) DEFAULT 1.0000, -- For multi-currency support
    original_amount NUMERIC(10,2), -- Original amount if currency conversion occurred
    original_currency TEXT, -- Original currency if conversion occurred
    
    -- Payment Method Information
    payment_method TEXT NOT NULL CHECK (payment_method IN ('mobile_money', 'card', 'bank_transfer')),
    payment_type TEXT, -- Specific type from Flutterwave (e.g., 'mobilemoneyzm', 'card')
    
    -- Mobile Money Specific (for Zambian providers)
    mobile_network TEXT CHECK (mobile_network IN ('MTN', 'AIRTEL', 'ZAMTEL', NULL)),
    mobile_number TEXT, -- Masked mobile number for security
    
    -- Card Specific
    card_last4 TEXT, -- Last 4 digits of card
    card_type TEXT, -- Visa, Mastercard, etc.
    card_bin TEXT, -- First 6 digits of card
    
    -- Customer Information
    customer_name TEXT NOT NULL,
    customer_email TEXT NOT NULL,
    customer_phone TEXT NOT NULL, -- In format +260XXXXXXXXX
    
    -- Transaction Status
    status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN (
        'pending',      -- Payment initiated, awaiting response
        'processing',   -- Payment being processed by provider
        'successful',   -- Payment completed successfully
        'failed',       -- Payment failed
        'cancelled',    -- Payment cancelled by user
        'refunded',     -- Payment refunded
        'disputed'      -- Payment disputed/under investigation
    )),
    
    -- Flutterwave Response Data
    flutterwave_status TEXT, -- Status from Flutterwave
    processor_response TEXT, -- Payment processor response message
    auth_model TEXT, -- Authentication model used
    
    -- Payment Links
    payment_link TEXT, -- Flutterwave checkout link
    redirect_url TEXT, -- URL to redirect after payment
    
    -- Verification & Security
    verified_at TIMESTAMP WITH TIME ZONE, -- When payment was verified
    verified_by UUID, -- Staff who verified (if manual verification needed)
    signature_verified BOOLEAN DEFAULT false, -- Webhook signature verification
    
    -- Settlement & Reconciliation
    settlement_status TEXT DEFAULT 'pending' CHECK (settlement_status IN (
        'pending',      -- Awaiting settlement
        'settled',      -- Settled to merchant account
        'partial',      -- Partially settled
        'disputed',     -- Under dispute
        'refunded'      -- Settlement refunded
    )),
    settlement_date TIMESTAMP WITH TIME ZONE, -- When funds were settled
    settlement_reference TEXT, -- Bank/settlement reference
    settlement_amount NUMERIC(10,2), -- Actual amount settled after all fees
    
    -- Business & Tax Information
    vat_amount NUMERIC(10,2) DEFAULT 0, -- VAT/Tax amount
    vat_percentage NUMERIC(5,2) DEFAULT 16.00, -- Zambian VAT is 16%
    receipt_number TEXT UNIQUE, -- Receipt/invoice number
    fiscal_year INTEGER, -- For accounting purposes
    accounting_period TEXT, -- E.g., '2025-Q4', '2025-11'
    
    -- Metadata
    metadata JSONB, -- Additional data from Flutterwave or custom data
    ip_address TEXT, -- IP address of payment initiator
    user_agent TEXT, -- Browser/device information
    device_fingerprint TEXT, -- Device identification for fraud detection
    geolocation JSONB, -- GPS coordinates if available
    
    -- Error Handling
    error_code TEXT, -- Error code if payment failed
    error_message TEXT, -- Error message details
    retry_count INTEGER DEFAULT 0, -- Number of retry attempts
    
    -- Timestamps
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    paid_at TIMESTAMP WITH TIME ZONE, -- When payment was completed
    failed_at TIMESTAMP WITH TIME ZONE, -- When payment failed
    expires_at TIMESTAMP WITH TIME ZONE, -- When payment link expires
    
    -- Constraints
    CONSTRAINT payment_transactions_pkey PRIMARY KEY (id),
    CONSTRAINT payment_transactions_company_id_fkey FOREIGN KEY (company_id) 
        REFERENCES public.companies(id) ON DELETE CASCADE,
    CONSTRAINT payment_transactions_outlet_id_fkey FOREIGN KEY (outlet_id) 
        REFERENCES public.outlets(id) ON DELETE SET NULL,
    CONSTRAINT payment_transactions_user_id_fkey FOREIGN KEY (user_id) 
        REFERENCES public.profiles(id) ON DELETE CASCADE,
    CONSTRAINT payment_transactions_parcel_id_fkey FOREIGN KEY (parcel_id) 
        REFERENCES public.parcels(id) ON DELETE SET NULL,
    CONSTRAINT payment_transactions_verified_by_fkey FOREIGN KEY (verified_by) 
        REFERENCES public.profiles(id) ON DELETE SET NULL
);

-- =====================================================
-- Indexes for Performance
-- =====================================================

-- Index on tx_ref for quick lookup
CREATE INDEX idx_payment_transactions_tx_ref ON public.payment_transactions(tx_ref);

-- Index on flutterwave_tx_id for verification
CREATE INDEX idx_payment_transactions_flw_tx_id ON public.payment_transactions(flutterwave_tx_id);

-- Index on parcel_id for linking to parcels
CREATE INDEX idx_payment_transactions_parcel_id ON public.payment_transactions(parcel_id);

-- Index on status for filtering
CREATE INDEX idx_payment_transactions_status ON public.payment_transactions(status);

-- Index on company_id for multi-tenant filtering
CREATE INDEX idx_payment_transactions_company_id ON public.payment_transactions(company_id);

-- Index on user_id for user payment history
CREATE INDEX idx_payment_transactions_user_id ON public.payment_transactions(user_id);

-- Index on created_at for date range queries
CREATE INDEX idx_payment_transactions_created_at ON public.payment_transactions(created_at DESC);

-- Composite index for company reports
CREATE INDEX idx_payment_transactions_company_status_date 
    ON public.payment_transactions(company_id, status, created_at DESC);

-- Index on mobile_network for Zambian provider reports
CREATE INDEX idx_payment_transactions_mobile_network 
    ON public.payment_transactions(mobile_network) WHERE mobile_network IS NOT NULL;

-- Index on settlement status for reconciliation
CREATE INDEX idx_payment_transactions_settlement_status 
    ON public.payment_transactions(settlement_status);

-- Index on receipt_number for quick lookup
CREATE INDEX idx_payment_transactions_receipt_number 
    ON public.payment_transactions(receipt_number) WHERE receipt_number IS NOT NULL;

-- Index on accounting_period for financial reports
CREATE INDEX idx_payment_transactions_accounting_period 
    ON public.payment_transactions(accounting_period);

-- Composite index for settlement queries
CREATE INDEX idx_payment_transactions_settlement 
    ON public.payment_transactions(company_id, settlement_status, settlement_date DESC);

-- =====================================================
-- Trigger for Updated At
-- =====================================================

CREATE OR REPLACE FUNCTION update_payment_transactions_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER payment_transactions_updated_at
    BEFORE UPDATE ON public.payment_transactions
    FOR EACH ROW
    EXECUTE FUNCTION update_payment_transactions_updated_at();

-- =====================================================
-- Trigger to Update Parcel Payment Status
-- =====================================================

CREATE OR REPLACE FUNCTION update_parcel_payment_status()
RETURNS TRIGGER AS $$
BEGIN
    -- If payment is successful, update parcel payment status
    IF NEW.status = 'successful' AND NEW.parcel_id IS NOT NULL THEN
        UPDATE public.parcels
        SET 
            payment_status = 'paid',
            delivery_fee = NEW.amount,
            updated_at = NOW()
        WHERE id = NEW.parcel_id;
    END IF;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER update_parcel_on_payment_success
    AFTER UPDATE OF status ON public.payment_transactions
    FOR EACH ROW
    WHEN (NEW.status = 'successful')
    EXECUTE FUNCTION update_parcel_payment_status();

-- =====================================================
-- Add Payment Method to Existing Payments Table
-- =====================================================
-- Update the existing payments table to include Flutterwave reference

ALTER TABLE public.payments 
    ADD COLUMN IF NOT EXISTS payment_transaction_id UUID,
    ADD COLUMN IF NOT EXISTS provider TEXT DEFAULT 'flutterwave',
    ADD CONSTRAINT payments_payment_transaction_id_fkey 
        FOREIGN KEY (payment_transaction_id) 
        REFERENCES public.payment_transactions(id) ON DELETE SET NULL;

-- Index for linking
CREATE INDEX IF NOT EXISTS idx_payments_payment_transaction_id 
    ON public.payments(payment_transaction_id);

-- =====================================================
-- Views for Reporting
-- =====================================================

-- View: Successful Payments by Provider
CREATE OR REPLACE VIEW v_successful_payments_by_provider AS
SELECT 
    company_id,
    mobile_network,
    payment_method,
    DATE_TRUNC('day', created_at) as payment_date,
    COUNT(*) as transaction_count,
    SUM(amount) as total_amount,
    SUM(transaction_fee) as total_fees,
    SUM(total_amount) as gross_total
FROM public.payment_transactions
WHERE status = 'successful'
GROUP BY company_id, mobile_network, payment_method, DATE_TRUNC('day', created_at)
ORDER BY payment_date DESC;

-- View: Payment Status Summary
CREATE OR REPLACE VIEW v_payment_status_summary AS
SELECT 
    company_id,
    status,
    payment_method,
    COUNT(*) as count,
    SUM(total_amount) as total_amount
FROM public.payment_transactions
GROUP BY company_id, status, payment_method;

-- View: Daily Mobile Money Transactions (Zambia)
CREATE OR REPLACE VIEW v_daily_mobile_money_zambia AS
SELECT 
    DATE_TRUNC('day', created_at) as transaction_date,
    mobile_network,
    COUNT(*) as transaction_count,
    SUM(amount) as delivery_fees,
    SUM(transaction_fee) as provider_fees,
    SUM(commission_amount) as total_commissions,
    SUM(net_amount) as net_revenue,
    SUM(vat_amount) as total_vat,
    SUM(total_amount) as total_revenue,
    AVG(amount) as avg_transaction_amount
FROM public.payment_transactions
WHERE 
    payment_method = 'mobile_money'
    AND status = 'successful'
    AND mobile_network IN ('MTN', 'AIRTEL', 'ZAMTEL')
GROUP BY DATE_TRUNC('day', created_at), mobile_network
ORDER BY transaction_date DESC, mobile_network;

-- View: Commission Summary by Company
CREATE OR REPLACE VIEW v_commission_summary AS
SELECT 
    company_id,
    DATE_TRUNC('month', created_at) as month,
    payment_method,
    mobile_network,
    COUNT(*) as transaction_count,
    SUM(amount) as gross_amount,
    AVG(commission_percentage) as avg_commission_rate,
    SUM(commission_amount) as total_commission,
    SUM(net_amount) as net_to_company,
    SUM(transaction_fee) as payment_provider_fees,
    SUM(vat_amount) as total_vat
FROM public.payment_transactions
WHERE status = 'successful'
GROUP BY company_id, DATE_TRUNC('month', created_at), payment_method, mobile_network
ORDER BY month DESC, company_id;

-- View: Settlement Tracking
CREATE OR REPLACE VIEW v_settlement_tracking AS
SELECT 
    company_id,
    settlement_status,
    COUNT(*) as transaction_count,
    SUM(amount) as total_amount,
    SUM(commission_amount) as total_commission,
    SUM(transaction_fee) as total_provider_fees,
    SUM(settlement_amount) as total_settlement_amount,
    MIN(created_at) as oldest_transaction,
    MAX(created_at) as newest_transaction,
    COUNT(*) FILTER (WHERE settlement_date IS NULL) as pending_count
FROM public.payment_transactions
WHERE status = 'successful'
GROUP BY company_id, settlement_status
ORDER BY company_id, settlement_status;

-- View: VAT Report for Zambia
CREATE OR REPLACE VIEW v_vat_report_zambia AS
SELECT 
    DATE_TRUNC('month', created_at) as tax_month,
    company_id,
    COUNT(*) as transaction_count,
    SUM(amount) as gross_sales,
    vat_percentage,
    SUM(vat_amount) as total_vat_collected,
    SUM(amount - vat_amount) as net_sales,
    SUM(commission_amount) as commissions_paid
FROM public.payment_transactions
WHERE 
    status = 'successful'
    AND currency = 'ZMW'
GROUP BY DATE_TRUNC('month', created_at), company_id, vat_percentage
ORDER BY tax_month DESC, company_id;

-- =====================================================
-- Grant Permissions (adjust based on your RLS policies)
-- =====================================================

-- Grant access to authenticated users
GRANT SELECT, INSERT, UPDATE ON public.payment_transactions TO authenticated;
GRANT SELECT ON v_successful_payments_by_provider TO authenticated;
GRANT SELECT ON v_payment_status_summary TO authenticated;
GRANT SELECT ON v_daily_mobile_money_zambia TO authenticated;

-- =====================================================
-- Row Level Security (RLS) Policies
-- =====================================================

-- Enable RLS
ALTER TABLE public.payment_transactions ENABLE ROW LEVEL SECURITY;

-- Policy: Users can view their own transactions
CREATE POLICY "Users can view own payment transactions"
    ON public.payment_transactions
    FOR SELECT
    USING (
        auth.uid() = user_id 
        OR 
        auth.uid() IN (
            SELECT id FROM public.profiles 
            WHERE company_id = payment_transactions.company_id 
            AND role IN ('admin', 'company_admin', 'outlet_manager', 'super_admin')
        )
    );

-- Policy: Users can insert their own transactions
CREATE POLICY "Users can create payment transactions"
    ON public.payment_transactions
    FOR INSERT
    WITH CHECK (auth.uid() = user_id);

-- Policy: System can update transactions (for webhooks)
CREATE POLICY "System can update payment transactions"
    ON public.payment_transactions
    FOR UPDATE
    USING (true); -- This will be restricted by application logic and webhook verification

-- =====================================================
-- Sample Data for Testing
-- =====================================================

-- Insert sample test transaction (adjust UUIDs based on your data)
-- UNCOMMENT AND MODIFY FOR TESTING:
/*
INSERT INTO public.payment_transactions (
    tx_ref,
    company_id,
    user_id,
    amount,
    transaction_fee,
    total_amount,
    currency,
    payment_method,
    mobile_network,
    customer_name,
    customer_email,
    customer_phone,
    status
) VALUES (
    'PARCEL-TEST-' || NOW()::TEXT,
    '00000000-0000-0000-0000-000000000001', -- Replace with actual company_id
    '00000000-0000-0000-0000-000000000002', -- Replace with actual user_id
    50.00,
    1.50,
    51.50,
    'ZMW',
    'mobile_money',
    'MTN',
    'Test Customer',
    'test@example.com',
    '+260977123456',
    'pending'
);
*/

-- =====================================================
-- Comments for Documentation
-- =====================================================

COMMENT ON TABLE public.payment_transactions IS 
    'Stores all Flutterwave payment transactions for parcel delivery fees. Supports Mobile Money (MTN, Airtel, Zamtel) and Card payments in Zambia.';

COMMENT ON COLUMN public.payment_transactions.tx_ref IS 
    'Internal transaction reference generated by the system (format: PARCEL-timestamp-unique)';

COMMENT ON COLUMN public.payment_transactions.flutterwave_tx_id IS 
    'Flutterwave transaction ID returned after successful payment processing';

COMMENT ON COLUMN public.payment_transactions.mobile_network IS 
    'Zambian mobile money provider: MTN, AIRTEL, or ZAMTEL';

COMMENT ON COLUMN public.payment_transactions.transaction_fee IS 
    'Fee charged by payment provider (Flutterwave) for processing the transaction';

COMMENT ON COLUMN public.payment_transactions.verified_at IS 
    'Timestamp when payment was verified through Flutterwave verification API';

COMMENT ON COLUMN public.payment_transactions.signature_verified IS 
    'Boolean flag indicating if webhook signature was successfully verified';

-- =====================================================
-- End of Schema
-- =====================================================
