export type UserRole = 'platform_admin' | 'company_admin' | 'agent';
export type Availability = 'online' | 'busy' | 'away' | 'offline';
export type ConversationStatus = 'waiting' | 'open' | 'pending' | 'closed';
export type SenderType = 'visitor' | 'agent' | 'system';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    availability: Availability;
    company: { id: number; name: string } | null;
}

export interface ReverbClientConfig {
    key: string;
    host: string;
    port: number;
    scheme: 'http' | 'https';
}

export interface SharedProps {
    appName: string;
    auth: { user: AuthUser | null };
    reverb: ReverbClientConfig;
    flash: { success?: string | null; error?: string | null; status?: string | null };
    errors: Record<string, string>;
    [key: string]: unknown;
}

export interface Message {
    id: string;
    seq: number;
    conversation_id: string;
    sender_type: SenderType;
    author_name: string;
    body: string;
    client_message_id: string | null;
    created_at: string;
    read_at: string | null;
    /** somente no cliente: mensagem otimista ainda não confirmada */
    pending?: boolean;
    failed?: boolean;
}

export interface ConversationSummary {
    id: string;
    status: ConversationStatus;
    status_label: string;
    priority: number;
    origin_url: string | null;
    agent_unread_count: number;
    created_at: string;
    last_message_at: string | null;
    first_response_at: string | null;
    closed_at: string | null;
    visitor: {
        id: string;
        name: string;
        email: string | null;
        phone: string | null;
        last_seen_at: string | null;
    };
    widget: { name: string };
    assignee: { id: number; name: string } | null;
    last_message: { body: string; sender_type: SenderType; created_at: string } | null;
}

export interface AgentOption {
    id: number;
    name: string;
    availability: Availability;
}
