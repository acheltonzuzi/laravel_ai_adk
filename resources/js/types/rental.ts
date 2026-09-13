export type RentalStatusValue =
    | 'pendente'
    | 'confirmado'
    | 'em_andamento'
    | 'concluido'
    | 'cancelado';

export type RentalCarSummary = {
    id: number;
    code: string;
    brand: string;
    model: string;
    license_plate: string;
};

export type RentalUserSummary = {
    id: number;
    name: string;
    email: string;
};

export type ManagedRental = {
    id: number;
    user_id: number;
    car_id: number;
    start_date: string;
    end_date: string;
    days: number;
    daily_rate: number;
    total: number;
    status: RentalStatusValue;
    status_label: string;
    created_at: string | null;
    car: RentalCarSummary;
    user: RentalUserSummary;
};

export type RentalCarOption = {
    id: number;
    code: string;
    brand: string;
    model: string;
    license_plate: string;
    price: number | null;
};

export type RentalUserOption = {
    id: number;
    name: string;
    email: string;
};

export type RentalFilters = {
    search: string;
    status: string;
    car_id: string;
    user_id: string;
};
