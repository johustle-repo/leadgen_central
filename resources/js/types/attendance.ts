export type AttendanceEntryType = 'time_in' | 'time_out';

export type AttendanceStatus = 'no_time_in' | 'on_time' | 'late' | null;

export type AttendanceUser = {
    id: number;
    name: string;
    role: string;
    team: string | null;
    status: string;
    qr_value: string;
};

export type AttendanceRecord = {
    id: number;
    user_id: number;
    user_name: string | null;
    entry_type: AttendanceEntryType;
    recorded_at: string;
    status: AttendanceStatus;
    late_minutes: number;
};

export type AttendanceDayStatus = 'no_time_in' | 'on_time' | 'late' | 'holiday';

export type AttendanceMonthDay = {
    date: string;
    time_in: string | null;
    time_out: string | null;
    worked_minutes: number;
    worked_minutes_label: string;
    status: AttendanceDayStatus;
    holiday_label: string | null;
};

export type AttendanceMonthlyAgent = {
    user_id: number;
    user_name: string;
    role_label: string;
    employee_code: string | null;
    alias_name: string | null;
    alias_email: string | null;
    status: string;
    added_at: string | null;
    days: AttendanceMonthDay[];
};

export type AttendanceCalendarDay = {
    date: string;
    label: 'Work day' | 'Rest Day' | 'Holiday';
    holiday_name: string | null;
    is_real: boolean;
};

export type AttendanceDailyMonitorRow = {
    user_id: number;
    user_name: string;
    employee_code: string | null;
    time_in: string | null;
    time_out: string | null;
    worked_minutes_label: string;
    status: AttendanceDayStatus;
    holiday_label: string | null;
};

export type AttendanceManualEntryAgent = {
    id: number;
    name: string;
    employee_code: string | null;
};

export type AttendanceCheckIn = {
    id: number;
    user_name: string | null;
    employee_code: string | null;
    entry_type: AttendanceEntryType;
    recorded_at: string;
};
