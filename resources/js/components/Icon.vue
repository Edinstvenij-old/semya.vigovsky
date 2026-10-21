<script setup>
import {
    ArrowLeft, Bed, Bell, BellOff, BellRing, Building2, Bus, CalendarDays, Car, Check, ChevronLeft, ChevronRight,
    CircleCheck, ClipboardList, Clock, Copy, DatabaseBackup, Download, DoorOpen, Ellipsis, ExternalLink, Eye,
    FileText, Folder, Home, House, KeyRound, Link, ListChecks, LogOut, MapPin, Menu, MessageCircle, MessageSquare,
    Paperclip, Pencil, Pin, Plane, PlaneLanding, PlaneTakeoff, Plug, Plus, RefreshCw, Search, Send, Settings,
    Shield, ShieldCheck, ShoppingCart, Siren, Trash2, TrainFront, UserPlus, Users, Wrench, X, Zap, Filter, Droplets,
    Camera, Lock, Image,
} from 'lucide-vue-next';
import { computed } from 'vue';

const icons = {
    'arrow-left': ArrowLeft, bed: Bed, bell: Bell, 'bell-off': BellOff, 'bell-ring': BellRing, 'building-2': Building2,
    bus: Bus, 'calendar-days': CalendarDays, car: Car, check: Check, 'chevron-left': ChevronLeft,
    'chevron-right': ChevronRight, 'circle-check': CircleCheck, 'clipboard-list': ClipboardList, clock: Clock,
    copy: Copy, backup: DatabaseBackup, download: Download, 'door-open': DoorOpen, more: Ellipsis,
    'external-link': ExternalLink, eye: Eye, 'file-text': FileText, folder: Folder, home: Home, house: House,
    'key-round': KeyRound, link: Link, 'list-checks': ListChecks, 'log-out': LogOut, 'map-pin': MapPin, menu: Menu,
    'message-circle': MessageCircle, 'message-square': MessageSquare, paperclip: Paperclip, pencil: Pencil, pin: Pin,
    plane: Plane, 'plane-landing': PlaneLanding, 'plane-takeoff': PlaneTakeoff, plug: Plug, plus: Plus,
    'refresh-cw': RefreshCw, search: Search, send: Send, settings: Settings, shield: Shield, 'shield-check': ShieldCheck,
    'shopping-cart': ShoppingCart, siren: Siren, trash: Trash2, 'train-front': TrainFront, 'user-plus': UserPlus,
    users: Users, wrench: Wrench, x: X, zap: Zap, filter: Filter, droplets: Droplets, camera: Camera, lock: Lock,
    image: Image,
};

const props = defineProps({ name: { type: String, required: true }, size: { type: [Number, String], default: '1.25em' } });
const component = computed(() => icons[props.name] || Folder);
</script>

<template>
    <component :is="component" :size="size" :stroke-width="2.25" aria-hidden="true" class="shrink-0" />
</template>
