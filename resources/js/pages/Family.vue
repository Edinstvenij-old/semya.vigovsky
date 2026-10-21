<script setup>
import { onMounted, reactive, ref } from 'vue';
import { api, errorText } from '../api';
import Avatar from '../components/Avatar.vue';
import Icon from '../components/Icon.vue';
import Modal from '../components/Modal.vue';
import { bytes, dateTime } from '../format';
import { loadMembers, setUser, store, toast } from '../store';
import { t } from '../i18n';

const family = ref(null);
const members = ref([]);
const invitations = ref([]);
const backups = ref([]);
const inviteForm = reactive({ role: 'member', note: '', hours: 72, max_uses: 1 });
const newLink = ref(null);
const editingMember = ref(null);
const backupBusy = ref(false);
const resetLink = ref(null);
const alertRegions = ref([]);
const alertsConfigured = ref(false);

const ROLES = {
    admin: { label: t('Администратор'), hint: t('Приглашает, меняет роли, настраивает резервные копии') },
    member: { label: t('Участник'), hint: t('Задачи, чаты, статусы в пути, документы') },
    viewer: { label: t('Наблюдатель'), hint: t('Упрощённый режим: только просмотр фото и статусов') },
};

async function load() {
    const { data } = await api.get('/family/members');
    family.value = data.family;
    members.value = data.members;
    store.members = data.members;
    if (store.user.is_admin) {
        const alerts = (await api.get('/alerts')).data;
        alertRegions.value = alerts.available;
        alertsConfigured.value = alerts.configured;
        family.value.alert_regions = alerts.watched;
        const [inv, bk] = await Promise.all([api.get('/family/invitations'), api.get('/family/backups')]);
        invitations.value = inv.data;
        backups.value = bk.data;
    }
}

onMounted(load);

async function createInvite() {
    try {
        newLink.value = (await api.post('/family/invitations', inviteForm)).data;
        inviteForm.note = '';
        load();
    } catch (e) {
        toast(errorText(e), 'error');
    }
}

async function copyLink() {
    await navigator.clipboard?.writeText(newLink.value.url);
    toast(t('Ссылка скопирована — отправьте её родственнику лично'));
}

async function share() {
    try {
        await navigator.share({ title: t('Приглашение в семью «:p0»', { p0: family.value.name }), url: newLink.value.url });
    } catch {
        copyLink();
    }
}

async function revoke(inv) {
    await api.delete(`/family/invitations/${inv.id}`);
    load();
}

async function saveMember() {
    try {
        await api.patch(`/family/members/${editingMember.value.id}`, { role: editingMember.value.role, relation: editingMember.value.relation });
        editingMember.value = null;
        load();
        loadMembers(true);
    } catch (e) {
        toast(errorText(e), 'error');
    }
}

async function removeMember() {
    if (!confirm(t('Исключить :p0 из семьи?', { p0: editingMember.value.name }))) return;
    try {
        await api.delete(`/family/members/${editingMember.value.id}`);
        editingMember.value = null;
        load();
    } catch (e) {
        toast(errorText(e), 'error');
    }
}

async function createResetLink() {
    try {
        const { data } = await api.post(`/family/members/${editingMember.value.id}/reset-link`);
        resetLink.value = { ...data, name: editingMember.value.name };
        editingMember.value = null;
    } catch (e) {
        toast(errorText(e), 'error');
    }
}

async function copyResetLink() {
    await navigator.clipboard?.writeText(resetLink.value.url);
    toast(t('Ссылка скопирована'));
}

async function saveFamily() {
    await api.patch('/family', {
        name: family.value.name,
        backup_frequency: family.value.backup_frequency,
        backup_keep: family.value.backup_keep,
        alert_regions: family.value.alert_regions,
    });
    setUser({ ...store.user, family: { ...store.user.family, name: family.value.name } });
    toast(t('Настройки семьи сохранены'));
}

async function runBackup() {
    backupBusy.value = true;
    try {
        backups.value = (await api.post('/family/backups')).data;
        toast(t('Резервная копия создана'));
    } catch (e) {
        toast(errorText(e), 'error');
    } finally {
        backupBusy.value = false;
    }
}

async function switchFamily(id) {
    const { data } = await api.post('/profile/family', { family_id: id });
    setUser(data.user);
    store.members = [];
    window.location.href = '/';
}
</script>

<template>
    <div v-if="family" class="space-y-6">
        <h1 class="page-title">{{ $t('Семья «:p0»', { p0: family.name }) }}</h1>

        <section v-if="store.user.families.length > 1" class="card">
            <h2 class="mb-3 text-xl font-bold">{{ $t('Мои семьи') }}</h2>
            <div class="flex flex-wrap gap-2">
                <button v-for="f in store.user.families" :key="f.id" class="btn"
                        :class="f.id === store.user.family.id ? 'bg-brand-700 text-white' : 'bg-white ring-2 ring-slate-300'"
                        @click="f.id !== store.user.family.id && switchFamily(f.id)">
                    <Icon name="house" /> {{ f.name }}
                </button>
            </div>
        </section>

        <section class="card" aria-labelledby="members">
            <h2 id="members" class="mb-3 flex items-center gap-2 text-xl font-bold"><Icon name="users" /> {{ $t('Участники (:p0)', { p0: members.length }) }}</h2>
            <ul class="divide-y divide-slate-200">
                <li v-for="m in members" :key="m.id" class="flex items-center gap-3 py-3">
                    <Avatar :user="m" />
                    <div class="min-w-0 flex-1">
                        <div class="font-bold">{{ m.name }} <span v-if="m.relation" class="font-normal text-slate-600">· {{ m.relation }}</span></div>
                        <div class="text-sm text-slate-600">{{ m.role_label }}<template v-if="store.user.is_admin"> · {{ m.email }}</template></div>
                    </div>
                    <button v-if="store.user.is_admin" class="btn-ghost px-3" :aria-label="$t('Изменить :p0', { p0: m.name })" @click="editingMember = { ...m }"><Icon name="pencil" /></button>
                </li>
            </ul>
        </section>

        <template v-if="store.user.is_admin">
            <section class="card space-y-4" aria-labelledby="invite">
                <h2 id="invite" class="flex items-center gap-2 text-xl font-bold"><Icon name="user-plus" /> {{ $t('Пригласить родственника') }}</h2>
                <p class="text-slate-600">{{ $t('Ссылка одноразовая и действует ограниченное время. Отправьте её лично — в мессенджере или по SMS.') }}</p>
                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="createInvite">
                    <fieldset class="sm:col-span-2">
                        <legend class="field-label">{{ $t('Роль') }}</legend>
                        <div class="grid gap-2 sm:grid-cols-3">
                            <label v-for="(r, key) in ROLES" :key="key" class="flex cursor-pointer gap-3 rounded-xl p-3 ring-2"
                                   :class="inviteForm.role === key ? 'bg-brand-50 ring-brand-600' : 'ring-slate-200'">
                                <input v-model="inviteForm.role" type="radio" :value="key" class="mt-1 h-5 w-5">
                                <span><b class="block">{{ r.label }}</b><span class="text-sm text-slate-600">{{ r.hint }}</span></span>
                            </label>
                        </div>
                    </fieldset>
                    <div>
                        <label class="field-label" for="inv-note">{{ $t('Для кого (заметка)') }}</label>
                        <input id="inv-note" v-model="inviteForm.note" class="field" :placeholder="$t('Бабушка Валя')">
                    </div>
                    <div>
                        <label class="field-label" for="inv-hours">{{ $t('Срок действия') }}</label>
                        <select id="inv-hours" v-model.number="inviteForm.hours" class="field">
                            <option :value="24">{{ $t('1 день') }}</option>
                            <option :value="72">{{ $t('3 дня') }}</option>
                            <option :value="168">{{ $t('7 дней') }}</option>
                        </select>
                    </div>
                    <button class="btn-primary sm:col-span-2"><Icon name="link" /> {{ $t('Создать ссылку') }}</button>
                </form>

                <div v-if="invitations.length">
                    <h3 class="mb-2 font-bold">{{ $t('Приглашения') }}</h3>
                    <ul class="space-y-2">
                        <li v-for="inv in invitations" :key="inv.id" class="flex flex-wrap items-center gap-2 rounded-xl bg-slate-50 p-3">
                            <span class="flex-1">
                                <b>{{ inv.note || $t('Без заметки') }}</b> · {{ inv.role_label }}
                                <span class="block text-sm text-slate-600">
                                    <template v-if="inv.revoked_at">{{ $t('отозвано') }}</template>
                                    <template v-else-if="inv.uses >= inv.max_uses">{{ $t('использовано') }}</template>
                                    <template v-else-if="!inv.valid">{{ $t('истекло') }}</template>
                                    <template v-else>{{ $t('действует до :p0', { p0: dateTime(inv.expires_at) }) }}</template>
                                </span>
                            </span>
                            <button v-if="inv.valid" class="btn-ghost text-red-700" @click="revoke(inv)">{{ $t('Отозвать') }}</button>
                        </li>
                    </ul>
                </div>
            </section>

            <section class="card space-y-4" aria-labelledby="settings">
                <h2 id="settings" class="flex items-center gap-2 text-xl font-bold"><Icon name="backup" /> {{ $t('Настройки и резервные копии') }}</h2>
                <form class="grid gap-4 sm:grid-cols-3" @submit.prevent="saveFamily">
                    <div>
                        <label class="field-label" for="f-name">{{ $t('Название семьи') }}</label>
                        <input id="f-name" v-model="family.name" class="field" required>
                    </div>
                    <div>
                        <label class="field-label" for="f-bk">{{ $t('Автоматическая копия') }}</label>
                        <select id="f-bk" v-model="family.backup_frequency" class="field">
                            <option value="off">{{ $t('Выключено') }}</option>
                            <option value="daily">{{ $t('Каждый день') }}</option>
                            <option value="weekly">{{ $t('Каждую неделю') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label" for="f-keep">{{ $t('Хранить копий') }}</label>
                        <input id="f-keep" v-model.number="family.backup_keep" type="number" min="1" max="60" class="field">
                    </div>
                    <fieldset class="sm:col-span-3">
                        <legend class="field-label">{{ $t('Тревоги на главной: какие области показывать') }}</legend>
                        <p v-if="!alertsConfigured" class="mb-2 text-sm text-slate-600">{{ $t('Чтобы карточка тревог появилась, на сервере нужен ключ ALERTS_IN_UA_TOKEN (бесплатно на alerts.in.ua).') }}</p>
                        <div class="grid max-h-64 gap-1 overflow-y-auto rounded-xl bg-slate-50 p-2 sm:grid-cols-2">
                            <label v-for="r in alertRegions" :key="r" class="flex min-h-11 items-center gap-2 rounded-lg px-2 hover:bg-white">
                                <input v-model="family.alert_regions" type="checkbox" :value="r" class="h-5 w-5"> {{ r }}
                            </label>
                        </div>
                    </fieldset>
                    <button class="btn-primary sm:col-span-3">{{ $t('Сохранить') }}</button>
                </form>
                <div class="flex flex-wrap items-center gap-3">
                    <button class="btn-secondary" :disabled="backupBusy" @click="runBackup"><Icon name="backup" /> {{ backupBusy ? $t('Создаём…') : $t('Создать копию сейчас') }}</button>
                    <span class="text-slate-600">{{ $t('Архив содержит все данные семьи и файлы (JSON + фото).') }}</span>
                </div>
                <ul v-if="backups.length" class="space-y-1">
                    <li v-for="b in backups" :key="b.name" class="flex items-center gap-3 rounded-xl bg-slate-50 p-2">
                        <span class="flex-1">{{ dateTime(b.created_at) }} · {{ bytes(b.size) }}</span>
                        <a :href="`/api/family/backups/${b.name}`" class="btn-ghost"><Icon name="download" /> {{ $t('Скачать') }}</a>
                    </li>
                </ul>
            </section>
        </template>

        <Modal v-if="newLink" :title="$t('Ссылка-приглашение')" @close="newLink = null">
            <p class="mb-3">{{ $t('Ссылка показывается') }} <b>{{ $t('только один раз') }}</b>{{ $t('. Скопируйте и отправьте её родственнику.') }}</p>
            <input :value="newLink.url" class="field mb-4 font-mono text-sm" readonly :aria-label="$t('Ссылка')" @focus="$event.target.select()">
            <div class="grid grid-cols-2 gap-2">
                <button class="btn-primary" @click="copyLink"><Icon name="copy" /> {{ $t('Копировать') }}</button>
                <button class="btn-secondary" @click="share"><Icon name="send" /> {{ $t('Поделиться') }}</button>
            </div>
        </Modal>

        <Modal v-if="resetLink" :title="$t('Сброс пароля: :p0', { p0: resetLink.name })" @close="resetLink = null">
            <p class="mb-3">{{ $t('Передайте ссылку лично (мессенджер, SMS). Она одноразовая и действует :p0 ч. Старый пароль работает, пока не задан новый.', { p0: Math.round(resetLink.expires_in_minutes / 60) }) }}</p>
            <input :value="resetLink.url" class="field mb-4 font-mono text-sm" readonly :aria-label="$t('Ссылка')" @focus="$event.target.select()">
            <button class="btn-primary w-full" @click="copyResetLink"><Icon name="copy" /> {{ $t('Копировать') }}</button>
        </Modal>

        <Modal v-if="editingMember" :title="editingMember.name" @close="editingMember = null">
            <form class="space-y-4" @submit.prevent="saveMember">
                <div>
                    <label class="field-label" for="em-role">{{ $t('Роль') }}</label>
                    <select id="em-role" v-model="editingMember.role" class="field">
                        <option v-for="(r, key) in ROLES" :key="key" :value="key">{{ r.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="field-label" for="em-rel">{{ $t('Кем приходится') }}</label>
                    <input id="em-rel" v-model="editingMember.relation" class="field">
                </div>
                <button class="btn-primary w-full">{{ $t('Сохранить') }}</button>
                <button v-if="editingMember.id !== store.user.id" type="button" class="btn-secondary w-full" @click="createResetLink">
                    <Icon name="key-round" /> {{ $t('Ссылка для сброса пароля') }}
                </button>
                <button v-if="editingMember.id !== store.user.id" type="button" class="btn-ghost w-full text-red-700" @click="removeMember">
                    <Icon name="trash" /> {{ $t('Исключить из семьи') }}
                </button>
            </form>
        </Modal>
    </div>
</template>
