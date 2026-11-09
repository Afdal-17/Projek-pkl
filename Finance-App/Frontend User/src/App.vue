<template>
  <div class="min-h-screen bg-gray-100 flex flex-col">
    <!-- Navbar -->
    <header class="bg-indigo-600 text-white shadow">
      <div class="max-w-7xl mx-auto px-4 py-4 flex flex-col sm:flex-row justify-between items-center gap-4">
        <h1 class="text-xl font-bold">Finance App - User Frontend (Vue.js)</h1>
        
        <!-- Navigation Tabs (Clickable Profile & Notifications) -->
        <nav class="flex items-center space-x-2">
          <button 
            @click="currentTab = 'transfer'" 
            :class="currentTab === 'transfer' ? 'bg-indigo-700 text-white' : 'text-indigo-100 hover:bg-indigo-500'"
            class="px-3 py-1.5 rounded-lg text-sm font-medium transition cursor-pointer relative z-20">
            Dompet & Transfer
          </button>
          <button 
            @click="currentTab = 'notifications'; fetchNotifications()" 
            :class="currentTab === 'notifications' ? 'bg-indigo-700 text-white' : 'text-indigo-100 hover:bg-indigo-500'"
            class="px-3 py-1.5 rounded-lg text-sm font-medium transition flex items-center gap-1 cursor-pointer relative z-20">
            Notifikasi
            <span v-if="unreadCount > 0" class="bg-red-500 text-white text-xs px-1.5 py-0.2 rounded-full">{{ unreadCount }}</span>
          </button>
          <button 
            @click="currentTab = 'profile'; fetchProfile()" 
            :class="currentTab === 'profile' ? 'bg-indigo-700 text-white' : 'text-indigo-100 hover:bg-indigo-500'"
            class="px-3 py-1.5 rounded-lg text-sm font-medium transition cursor-pointer relative z-20">
            Profil & Akun
          </button>
        </nav>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 py-6">

      <!-- TAB 1: DOMPET & TRANSFER -->
      <div v-if="currentTab === 'transfer'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Left Column: Wallets -->
        <div class="md:col-span-1 space-y-6">
          <div class="bg-white rounded-xl shadow p-5">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center justify-between">
              <span>Dompet Saya</span>
              <span class="text-xs text-indigo-600 bg-indigo-50 px-2 py-1 rounded">Aktif Penerima</span>
            </h2>
            
            <div v-if="loadingWallets" class="text-center py-4 text-gray-500">Memuat dompet...</div>
            
            <div v-else class="space-y-4">
              <div v-for="dompet in wallets" :key="dompet.id" class="border rounded-lg p-4 bg-gray-50 hover:bg-white transition shadow-sm">
                <div class="flex justify-between items-start mb-2">
                  <div>
                    <h3 class="font-bold text-gray-800">{{ dompet.nama }}</h3>
                    <p class="text-xs text-gray-500">{{ dompet.kategori }}</p>
                  </div>
                  <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" :checked="dompet.terima_transfer" @change="toggleReceiver(dompet.id)" class="sr-only peer">
                    <div class="w-9 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                  </label>
                </div>
                <div class="text-right mt-3">
                  <span class="text-xs text-gray-400 block">Saldo</span>
                  <span class="text-lg font-extrabold text-indigo-600 break-words">Rp {{ formatNumber(dompet.saldo) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column: Transfer -->
        <div class="md:col-span-2 space-y-6">
          <div class="bg-white rounded-xl shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Kirim Transfer Antar User</h2>
            
            <form @submit.prevent="submitTransfer" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Cari Dompet Penerima (Aktif Transfer)</label>
                <input 
                  type="text" 
                  v-model="searchQuery" 
                  @input="searchRecipients"
                  placeholder="Ketik nama penerima..." 
                  class="w-full border rounded-lg px-3 py-2 focus:ring focus:ring-indigo-200 outline-none"
                >
                <div v-if="recipients.length > 0" class="mt-2 border rounded-lg bg-white shadow-lg max-h-40 overflow-y-auto">
                  <div 
                    v-for="rec in recipients" 
                    :key="rec.id" 
                    @click="selectRecipient(rec)"
                    class="p-3 hover:bg-indigo-50 cursor-pointer border-b last:border-b-0 flex justify-between items-center"
                  >
                    <div>
                      <p class="font-semibold text-sm">{{ rec.user_name }}</p>
                      <p class="text-xs text-gray-500">Dompet: {{ rec.nama }}</p>
                    </div>
                    <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">Aktif Terima</span>
                  </div>
                </div>
                <div v-if="selectedRecipient" class="mt-2 p-2 bg-indigo-50 border border-indigo-200 rounded text-xs text-indigo-700">
                  Penerima dipilih: <b>{{ selectedRecipient.user_name }}</b> (Dompet: {{ selectedRecipient.nama }})
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Transfer</label>
                <input 
                  type="number" 
                  step="0.01" 
                  v-model="transferAmount" 
                  placeholder="0.00" 
                  class="w-full border rounded-lg px-3 py-2 focus:ring focus:ring-indigo-200 outline-none"
                  required
                >
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                <input 
                  type="text" 
                  v-model="transferNote" 
                  placeholder="Catatan transfer..." 
                  class="w-full border rounded-lg px-3 py-2 focus:ring focus:ring-indigo-200 outline-none"
                >
              </div>

              <button 
                type="submit" 
                :disabled="submitting"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 rounded-lg transition shadow disabled:opacity-50"
              >
                {{ submitting ? 'Memproses...' : 'Kirim Transfer Sekarang' }}
              </button>
            </form>

            <div v-if="message" class="mt-4 p-3 rounded-lg text-sm" :class="success ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
              {{ message }}
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 2: NOTIFIKASI -->
      <div v-if="currentTab === 'notifications'" class="max-w-2xl mx-auto bg-white rounded-xl shadow p-6 space-y-6">
        <div class="flex justify-between items-center border-b pb-4">
          <h2 class="text-lg font-semibold text-gray-800">Daftar Notifikasi</h2>
          <button @click="markAllRead" class="text-sm text-indigo-600 hover:underline">Tandai semua dibaca</button>
        </div>

        <div v-if="loadingNotifications" class="text-center py-8 text-gray-500">Memuat notifikasi...</div>
        
        <div v-else-if="notifications.length === 0" class="text-center py-8 text-gray-400">Belum ada notifikasi.</div>

        <div v-else class="space-y-3">
          <div v-for="notif in notifications" :key="notif.id" 
               :class="notif.read ? 'bg-gray-50' : 'bg-indigo-50 border-l-4 border-indigo-600'" 
               class="p-4 rounded-lg border shadow-sm flex justify-between items-start">
            <div>
              <p class="font-medium text-gray-900 text-sm">{{ notif.text }}</p>
              <span class="text-xs text-gray-400 mt-1 block">{{ notif.scope }}</span>
            </div>
            <button v-if="!notif.read" @click="markAsRead(notif.id)" class="text-xs bg-indigo-600 text-white px-2.5 py-1 rounded hover:bg-indigo-700">
              Dibaca
            </button>
          </div>
        </div>
      </div>

      <!-- TAB 3: PROFIL & AKUN -->
      <div v-if="currentTab === 'profile'" class="max-w-xl mx-auto bg-white rounded-xl shadow p-6 space-y-6">
        <h2 class="text-lg font-semibold text-gray-800 border-b pb-4">Profil Pengguna</h2>

        <div v-if="loadingProfile" class="text-center py-8 text-gray-500">Memuat profil...</div>

        <div v-else class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
            <input type="text" v-model="profileForm.nama" class="w-full border rounded-lg px-3 py-2 mt-1 focus:ring focus:ring-indigo-200 outline-none">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Email</label>
            <input type="email" v-model="profileForm.email" disabled class="w-full border rounded-lg px-3 py-2 mt-1 bg-gray-100 text-gray-500 cursor-not-allowed">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Mata Uang Preferensi</label>
            <select v-model="profileForm.mata_uang" class="w-full border rounded-lg px-3 py-2 mt-1 focus:ring focus:ring-indigo-200 outline-none">
              <option value="IDR">IDR (Rupiah)</option>
              <option value="USD">USD (Dollar)</option>
              <option value="EUR">EUR (Euro)</option>
            </select>
          </div>

          <button @click="updateProfile" :disabled="updatingProfile" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 rounded-lg transition shadow">
            {{ updatingProfile ? 'Menyimpan...' : 'Simpan Perubahan Profil' }}
          </button>

          <div v-if="profileMessage" class="p-3 rounded-lg text-sm" :class="profileSuccess ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
            {{ profileMessage }}
          </div>
        </div>
      </div>

    </main>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  data() {
    return {
      currentTab: 'transfer', // transfer, notifications, profile
      wallets: [],
      loadingWallets: true,
      searchQuery: '',
      recipients: [],
      selectedRecipient: null,
      transferAmount: '',
      transferNote: '',
      submitting: false,
      message: '',
      success: false,
      apiBase: 'http://127.0.0.1:8003/api',
      
      // Notifications
      notifications: [],
      loadingNotifications: false,
      unreadCount: 0,

      // Profile
      profileForm: { nama: '', email: '', mata_uang: 'IDR' },
      loadingProfile: false,
      updatingProfile: false,
      profileMessage: '',
      profileSuccess: false
    }
  },
  mounted() {
    this.fetchWallets()
    this.fetchNotificationsCount()
  },
  methods: {
    formatNumber(val) {
      return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2 })
    },
    async fetchWallets() {
      try {
        const res = await axios.get(`${this.apiBase}/dompet`, { withCredentials: true })
        this.wallets = res.data.data || res.data.dompets || res.data
      } catch (err) {
        console.error('Gagal mengambil dompet:', err)
      } finally {
        this.loadingWallets = false
      }
    },
    async toggleReceiver(id) {
      try {
        await axios.patch(`${this.apiBase}/dompet/${id}/toggle-transfer`, {}, { withCredentials: true })
        this.fetchWallets()
      } catch (err) {
        console.error(err)
      }
    },
    async searchRecipients() {
      if (this.searchQuery.length < 2) {
        this.recipients = []
        return
      }
      try {
        const res = await axios.get(`${this.apiBase}/transfer/recipients/search?query=${this.searchQuery}`, { withCredentials: true })
        this.recipients = res.data
      } catch (err) {
        console.error(err)
      }
    },
    selectRecipient(rec) {
      this.selectedRecipient = rec
      this.searchQuery = rec.user_name + ' (' + rec.nama + ')'
      this.recipients = []
    },
    async submitTransfer() {
      if (!this.selectedRecipient) {
        this.message = 'Pilih dompet penerima terlebih dahulu'
        this.success = false
        return
      }
      this.submitting = true
      this.message = ''
      try {
        const payload = {
          id_dompet_tujuan: this.selectedRecipient.id,
          jumlah: parseFloat(this.transferAmount),
          catatan: this.transferNote
        }
        await axios.post(`${this.apiBase}/transfer`, payload, { withCredentials: true })
        this.success = true
        this.message = 'Transfer berhasil dikirim!'
        this.transferAmount = ''
        this.transferNote = ''
        this.selectedRecipient = null
        this.searchQuery = ''
        this.fetchWallets()
      } catch (err) {
        this.success = false
        this.message = err.response?.data?.message || 'Gagal melakukan transfer'
      } finally {
        this.submitting = false
      }
    },
    async fetchNotificationsCount() {
      try {
        const res = await axios.get(`${this.apiBase}/notifications?read=0`, { withCredentials: true })
        this.unreadCount = res.data.length
      } catch (err) {
        console.error(err)
      }
    },
    async fetchNotifications() {
      this.loadingNotifications = true
      try {
        const res = await axios.get(`${this.apiBase}/notifications`, { withCredentials: true })
        this.notifications = res.data
        this.unreadCount = this.notifications.filter(n => !n.read).length
      } catch (err) {
        console.error(err)
      } finally {
        this.loadingNotifications = false
      }
    },
    async markAsRead(id) {
      try {
        await axios.patch(`${this.apiBase}/notifications/${id}/read`, {}, { withCredentials: true })
        this.fetchNotifications()
      } catch (err) {
        console.error(err)
      }
    },
    async markAllRead() {
      try {
        await axios.patch(`${this.apiBase}/notifications/read-all`, {}, { withCredentials: true })
        this.fetchNotifications()
      } catch (err) {
        console.error(err)
      }
    },
    async fetchProfile() {
      this.loadingProfile = true
      try {
        const res = await axios.get(`${this.apiBase}/user`, { withCredentials: true })
        this.profileForm.nama = res.data.nama
        this.profileForm.email = res.data.email
        this.profileForm.mata_uang = res.data.mata_uang || 'IDR'
      } catch (err) {
        console.error(err)
      } finally {
        this.loadingProfile = false
      }
    },
    async updateProfile() {
      this.updatingProfile = true
      this.profileMessage = ''
      try {
        await axios.patch(`${this.apiBase}/account`, this.profileForm, { withCredentials: true })
        this.profileSuccess = true
        this.profileMessage = 'Profil berhasil diperbarui!'
      } catch (err) {
        this.profileSuccess = false
        this.profileMessage = err.response?.data?.message || 'Gagal memperbarui profil'
      } finally {
        this.updatingProfile = false
      }
    }
  }
}
</script>
