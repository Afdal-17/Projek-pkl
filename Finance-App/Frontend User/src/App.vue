<template>
  <div class="min-h-screen bg-gray-100 flex flex-col">
    <!-- Navbar -->
    <header class="bg-indigo-600 text-white shadow">
      <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
        <h1 class="text-xl font-bold">Finance App - User Frontend (Vue.js)</h1>
        <div class="flex items-center space-x-4">
          <span class="text-sm bg-indigo-700 px-3 py-1 rounded-full">Connected to Laravel API</span>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 py-6 grid grid-cols-1 md:grid-cols-3 gap-6">
      
      <!-- Left Column: Wallets & Transfer Receiver Toggle -->
      <div class="md:col-span-1 space-y-6">
        <div class="bg-white rounded-xl shadow p-5">
          <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center justify-between">
            <span>Dompet Saya</span>
            <span class="text-xs text-indigo-600 bg-indigo-50 px-2 py-1 rounded">Aktif Penerima: 1</span>
          </h2>
          
          <div v-if="loadingWallets" class="text-center py-4 text-gray-500">Memuat dompet...</div>
          
          <div v-else class="space-y-4">
            <div v-for="dompet in wallets" :key="dompet.id" class="border rounded-lg p-4 bg-gray-50 hover:bg-white transition shadow-sm">
              <div class="flex justify-between items-start mb-2">
                <div>
                  <h3 class="font-bold text-gray-800">{{ dompet.nama }}</h3>
                  <p class="text-xs text-gray-500">{{ dompet.kategori }}</p>
                </div>
                <!-- Toggle Slider for Transfer Receiver -->
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

      <!-- Right Column: Transfer & Recipient Search -->
      <div class="md:col-span-2 space-y-6">
        <div class="bg-white rounded-xl shadow p-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-4">Kirim Transfer Antar User</h2>
          
          <form @submit.prevent="submitTransfer" class="space-y-4">
            <!-- Search Recipient -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Cari Dompet Penerima (Aktif Transfer)</label>
              <input 
                type="text" 
                v-model="searchQuery" 
                @input="searchRecipients"
                placeholder="Ketik nama penerima..." 
                class="w-full border rounded-lg px-3 py-2 focus:ring focus:ring-indigo-200 outline-none"
              >
              <!-- Recipient Results Dropdown -->
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

            <!-- Amount Input (Supports quadrillions) -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Transfer (Mendukung s.d Kuadriliun)</label>
              <input 
                type="number" 
                step="0.01" 
                v-model="transferAmount" 
                placeholder="0.00" 
                class="w-full border rounded-lg px-3 py-2 focus:ring focus:ring-indigo-200 outline-none"
                required
              >
            </div>

            <!-- Note / Description -->
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

          <!-- Notification feedback -->
          <div v-if="message" class="mt-4 p-3 rounded-lg text-sm" :class="success ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
            {{ message }}
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
      apiBase: 'http://127.0.0.1:8000'
    }
  },
  mounted() {
    this.fetchWallets()
  },
  methods: {
    formatNumber(val) {
      return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2 })
    },
    async fetchWallets() {
      try {
        const res = await axios.get(`${this.apiBase}/dompet`, { withCredentials: true })
        this.wallets = res.data.dompets || res.data
      } catch (err) {
        console.error('Gagal mengambil dompet:', err)
        // Mock data for initial preview if API not yet running
        this.wallets = [
          { id: 1, nama: 'Dompet Utama', kategori: 'Kas', saldo: 1500000000000.50, terima_transfer: true },
          { id: 2, nama: 'Tabungan Masa Depan', kategori: 'Tabungan', saldo: 500000000000000.00, terima_transfer: false }
        ]
      } finally {
        this.loadingWallets = false
      }
    },
    async toggleReceiver(id) {
      try {
        await axios.patch(`${this.apiBase}/dompet/${id}/toggle-transfer`, {}, { withCredentials: true })
        this.fetchWallets()
        this.showFeedback('Status penerima transfer berhasil diperbarui', true)
      } catch (err) {
        console.error(err)
        this.showFeedback('Gagal memperbarui status penerima', false)
      }
    },
    async searchRecipients() {
      if (this.searchQuery.length < 2) {
        this.recipients = []
        return
      }
      try {
        const res = await axios.get(`${this.apiBase}/transfer/recipients/search?q=${this.searchQuery}`, { withCredentials: true })
        this.recipients = res.data
      } catch (err) {
        console.error(err)
        // Mock search result for preview
        this.recipients = [
          { id: 10, user_name: 'Budi Santoso', nama: 'Dompet Budi', terima_transfer: true }
        ]
      }
    },
    selectRecipient(rec) {
      this.selectedRecipient = rec
      this.searchQuery = rec.user_name + ' (' + rec.nama + ')'
      this.recipients = []
    },
    async submitTransfer() {
      if (!this.selectedRecipient) {
        this.showFeedback('Silakan pilih penerima transfer terlebih dahulu', false)
        return
      }
      this.submitting = true
      try {
        await axios.post(`${this.apiBase}/transfer`, {
          penerima_id: this.selectedRecipient.id,
          jumlah: this.transferAmount,
          catatan: this.transferNote
        }, { withCredentials: true })
        this.showFeedback('Transfer berhasil dikirim!', true)
        this.transferAmount = ''
        this.transferNote = ''
        this.selectedRecipient = null
        this.searchQuery = ''
        this.fetchWallets()
      } catch (err) {
        this.showFeedback(err.response?.data?.message || 'Gagal melakukan transfer', false)
      } finally {
        this.submitting = false
      }
    },
    showFeedback(msg, isSuccess) {
      this.message = msg
      this.success = isSuccess
      setTimeout(() => {
        this.message = ''
      }, 4000)
    }
  }
}
</script>
