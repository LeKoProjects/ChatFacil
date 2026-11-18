import type { Message, ReverbClientConfig } from '@/types';

export interface WidgetConfig {
    key: string;
    host: string;
    title: string;
    company: string | null;
    primary_color: string;
    position: 'left' | 'right';
    welcome_message: string | null;
    offline_message: string | null;
    form: { ask_name: boolean; ask_email: boolean; ask_phone: boolean; require_email: boolean };
    sound_enabled: boolean;
    online: boolean;
    api_base: string;
    reverb: ReverbClientConfig;
}

export interface WidgetConversation {
    id: string;
    status: 'waiting' | 'open' | 'pending' | 'closed';
    channel: string;
    agent: string | null;
    unread: number;
}

export interface ConversationPayload {
    conversation: WidgetConversation | null;
    messages: Message[];
}

export interface SessionPayload extends ConversationPayload {
    token: string;
    visitor: { id: string; name: string | null; email: string | null };
}
