-- USERS TABLE
CREATE TABLE public.users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password TEXT NOT NULL,
    position VARCHAR(20) DEFAULT 'employee',
    date_joined DATE NOT NULL DEFAULT CURRENT_DATE,
    race VARCHAR(100),
    religion VARCHAR(100),
    reset_token VARCHAR(255),
    reset_expiry TIMESTAMP,
    saturday_cycle VARCHAR(4) NOT NULL DEFAULT 'work'
);

-- LEAVE TYPES TABLE
CREATE TABLE public.leave_types (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    annual_limit INTEGER DEFAULT 0,
    default_limit INTEGER DEFAULT 0
);

-- LEAVE BALANCES TABLE
CREATE TABLE public.leave_balances (
    id SERIAL PRIMARY KEY,
    user_id INTEGER,
    leave_type_id INTEGER,
    year INTEGER DEFAULT EXTRACT(YEAR FROM CURRENT_DATE),
    used_days INTEGER DEFAULT 0,
    carry_forward INTEGER DEFAULT 0,
    entitled_days INTEGER DEFAULT 0,
    total_available INTEGER,
    CONSTRAINT fk_lb_user FOREIGN KEY (user_id) REFERENCES public.users (id) ON DELETE CASCADE,
    CONSTRAINT fk_lb_leave_type FOREIGN KEY (leave_type_id) REFERENCES public.leave_types (id) ON DELETE CASCADE
);

-- LEAVE REQUESTS TABLE
CREATE TABLE public.leave_requests (
    id SERIAL PRIMARY KEY,
    user_id INTEGER,
    leave_type_id INTEGER,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_by INTEGER,
    decision_date TIMESTAMP,
    total_days NUMERIC,
    CONSTRAINT fk_lr_user FOREIGN KEY (user_id) REFERENCES public.users (id) ON DELETE CASCADE,
    CONSTRAINT fk_lr_leave_type FOREIGN KEY (leave_type_id) REFERENCES public.leave_types (id) ON DELETE SET NULL,
    CONSTRAINT fk_lr_approver FOREIGN KEY (approved_by) REFERENCES public.users (id) ON DELETE SET NULL
);

-- PUBLIC HOLIDAYS TABLE
CREATE TABLE public.public_holidays (
    id SERIAL PRIMARY KEY,
    holiday_date DATE NOT NULL,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) DEFAULT 'National',
    state VARCHAR(50)
);
