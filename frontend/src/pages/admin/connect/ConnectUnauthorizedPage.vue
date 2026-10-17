<script setup lang="ts">
import { ShieldAlert, ArrowLeft, Home } from 'lucide-vue-next';
</script>

<template>
  <div class="min-h-[60vh] flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-3xl border border-[#ebe8de] p-8 shadow-xs text-center space-y-6">
      <div class="h-16 w-16 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center mx-auto">
        <ShieldAlert class="w-8 h-8" />
      </div>

      <div class="space-y-2">
        <h1 class="text-xl font-bold text-neutral-900">Access Restricted</h1>
        <p class="text-xs text-neutral-500 leading-relaxed">
          You do not have sufficient administrative privileges to access <strong>MSA Connect Management</strong>.
        </p>
      </div>

      <div class="bg-[#faf8f5] rounded-2xl p-4 text-xs text-left border border-[#ebe8de] space-y-2">
        <div class="font-bold text-neutral-700">Required Roles or Permissions:</div>
        <ul class="list-disc list-inside space-y-1 text-neutral-600 font-mono text-[11px]">
          <li>Role: connect-administrator</li>
          <li>Role: connect-staff</li>
          <li>Permission: connect.view</li>
          <li>Permission: connect.manage</li>
        </ul>
        <p class="text-[11px] text-neutral-400 pt-1">
          Platform Super Administrators and Administrators also have full access.
        </p>
      </div>

      <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
        <router-link
          to="/admin"
          class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-[#640c0e] text-white text-xs font-semibold hover:bg-[#500a0b] transition-colors shadow-xs"
        >
          <ArrowLeft class="w-4 h-4" />
          <span>Central Admin</span>
        </router-link>
        <router-link
          to="/"
          class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-[#ebe8de] text-xs font-semibold text-neutral-700 hover:bg-[#faf8f5] transition-colors"
        >
          <Home class="w-4 h-4" />
          <span>Portal Home</span>
        </router-link>
      </div>
    </div>
  </div>
</template>
