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
