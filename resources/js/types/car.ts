export type CarConditionValue = 'novo' | 'bom' | 'regular' | 'danificado';

export type CarAvailabilityValue =
    | 'disponivel'
    | 'alugado'
    | 'vendido'
    | 'manutencao';

export type EnumOption = {
    value: string;
    label: string;
};

export type ManagedCar = {
    id: number;
    code: string;
    license_plate: string;
    brand: string;
    model: string;
    year: number;
    price: number | null;
    condition: CarConditionValue;
    condition_label: string;
    location: string | null;
    availability: CarAvailabilityValue;
    availability_label: string;
    image_url: string | null;
    created_at: string | null;
};

export type CarFilters = {
    search: string;
    condition: string;
    availability: string;
};
