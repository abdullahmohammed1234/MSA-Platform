<script setup lang="ts">
import { ref } from 'vue';

// Import all UI Components
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Select } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio';
import { Switch } from '@/components/ui/switch';

// Import Navigation Components
import { Navbar } from '@/components/navigation/navbar';
import { Sidebar } from '@/components/navigation/sidebar';
import { Footer } from '@/components/navigation/footer';
import { Drawer } from '@/components/navigation/drawer';
import { MobileNav } from '@/components/navigation/mobile-nav';

// Import Data Display Components
import { Table } from '@/components/data-display/table';
import { Chart } from '@/components/data-display/chart';
import { Card } from '@/components/data-display/card';
import { Badge, AchievementBadge, RoleBadge } from '@/components/data-display/badge';
import { LmsProgressBar, LmsCircularProgress, LmsMilestoneTracker } from '@/components/data-display/progress';

// Import Feedback Components
import { Alert } from '@/components/feedback/alert';
import { Dialog } from '@/components/feedback/dialog';
import { Loader } from '@/components/feedback/loader';
import { Skeleton } from '@/components/feedback/skeleton';
import { useToastStore, ToastContainer } from '@/components/feedback/toast';

// Local testing states
const toast = useToastStore();
const activeTab = ref('primitives');

// Form state binders
const textVal = ref('');
const passwordVal = ref('');
const textAraVal = ref('');
const selectedValue = ref('');
const selectOptions = [
  { label: 'Simon Fraser University', value: 'sfu' },
  { label: 'University of British Columbia', value: 'ubc' },
  { label: 'University of Victoria', value: 'uvic' }
];
const checkboxVal = ref(false);
const radioVal = ref('male');
const switchVal = ref(false);

// Dialog/Drawer state binders
const isDialogOpen = ref(false);
const isDrawerOpen = ref(false);
const isMobileNavOpen = ref(false);
const isFullscreenLoaderOpen = ref(false);

// Table mock data
const tableCols = [
  { key: 'name', label: 'Name', sortable: true },
  { key: 'role', label: 'Role', sortable: true },
  { key: 'hours', label: 'Hours', sortable: true },
  { key: 'status', label: 'Status', sortable: false }
];
const tableItems = ref([
  { id: 1, name: 'Abdullah Mohammed', role: 'Murshid', hours: 42, status: 'Active' },
  { id: 2, name: 'Sarah Ahmed', role: 'Mutallim', hours: 15, status: 'Active' },
  { id: 3, name: 'Yasmin Khan', role: 'Alim', hours: 120, status: 'Inactive' }
]);

// Chart mock data
const chartData = [
  { label: 'Jan', value: 120 },
  { label: 'Feb', value: 240 },
  { label: 'Mar', value: 180 },
  { label: 'Apr', value: 390 },
  { label: 'May', value: 310 },
  { label: 'Jun', value: 540 }
];

// Milestone mock data
const milestonesList = [
  { id: '1', label: 'Introduction to Arabic Syntax', description: 'Complete first 3 grammar lectures.', status: 'completed' as const },
  { id: '2', label: 'Fractions of Jurisprudence', description: 'Understand basic logic of rulings.', status: 'active' as const },
  { id: '3', label: 'Final Exam & Evaluation', description: 'Achieve pass score above 80%.', status: 'locked' as const }
];

// Mock sidebar links
const sidebarItems = [
  {
    label: 'Main',
    path: '#',
    children: [
      { label: 'Dashboard', path: '/academy/dashboard', icon: 'dashboard' },
      { label: 'Courses', path: '/academy/courses', icon: 'courses' }
    ]
  },
  {
    label: 'Account',
    path: '#',
    children: [
      { label: 'Settings', path: '/settings', icon: 'settings' }
    ]
  }
];

// Mock Navbar links
const navLinks = [
  { label: 'Home', path: '/' },
  { label: 'Academy', path: '/academy/dashboard' },
  { label: 'Showcase', path: '/design-system' }
];

const triggerFullscreenLoader = () => {
  isFullscreenLoaderOpen.value = true;
  setTimeout(() => {
    isFullscreenLoaderOpen.value = false;
    toast.success('System sync completed!');
  }, 2500);
};
</script>

<template>
  <div class="min-h-screen bg-neutral-background flex flex-col pb-20">
    <ToastContainer />
    
    <!-- Mini Inline Header -->
    <header class="bg-primary text-white py-8 px-4 sm:px-6 lg:px-8 shadow-premium select-none">
      <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
        <div>
          <h1 class="text-3xl font-display font-bold text-accent-gold tracking-wide">
            SFU MSA Shared Design System
          </h1>
          <p class="text-xs text-neutral-ivory/80 mt-1 uppercase tracking-[0.1em] font-mono">
            Phase 2 Reusable Component Showroom
          </p>
        </div>
        
        <router-link to="/">
          <Button variant="secondary" size="sm">
            Back to App Home
          </Button>
        </router-link>
      </div>
    </header>

    <!-- Navigation Showroom Tabs -->
    <div class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 mt-8">
      <div class="flex items-center gap-2 border-b border-neutral-ivory pb-2 mb-8 overflow-x-auto">
        <button
          v-for="tab in ['primitives', 'layouts', 'data', 'feedback']"
          :key="tab"
          @click="activeTab = tab"
          :class="[
            'px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-lg transition-colors cursor-pointer',
            activeTab === tab 
              ? 'bg-primary text-white' 
              : 'text-neutral-muted hover:text-primary hover:bg-neutral-ivory/50'
          ]"
        >
          {{ tab }}
        </button>
      </div>

      <!-- Tab 1: Core Primitives & Forms -->
      <section v-if="activeTab === 'primitives'" class="space-y-10">
        <!-- Buttons Section -->
        <Card variant="premium">
          <template #header>
            <h3 class="font-semibold text-lg">Buttons Showroom</h3>
          </template>
          <div class="flex flex-wrap gap-4 items-center">
            <Button variant="primary">Primary</Button>
            <Button variant="secondary">Secondary</Button>
            <Button variant="outline">Outline</Button>
            <Button variant="ghost">Ghost</Button>
            <Button variant="destructive">Destructive</Button>
            <Button variant="gold" isShiny>Gold (Shiny)</Button>
            <Button variant="success">Success</Button>
            <Button variant="link">Link Style</Button>
          </div>
          <div class="flex flex-wrap gap-4 items-center mt-6 pt-6 border-t border-neutral-ivory/30">
            <Button size="sm">Small</Button>
            <Button size="md">Medium</Button>
            <Button size="lg">Large</Button>
            <Button size="icon">
              <template #left-icon>
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
              </template>
            </Button>
            <Button isLoading>Processing</Button>
            <Button disabled>Disabled</Button>
          </div>
        </Card>

        <!-- Form fields -->
        <Card variant="default">
          <template #header>
            <h3 class="font-semibold text-lg">Form Inputs & Selects</h3>
          </template>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Text Inputs -->
            <div>
              <Input
                v-model="textVal"
                label="Full Name"
                placeholder="Enter your name..."
                required
                description="Use your legal matching university enrollment name."
              />
              <Input
                v-model="passwordVal"
                label="Password"
                type="password"
                placeholder="••••••••"
                error="Password must contain at least 8 characters."
              />
            </div>

            <!-- Select & Textarea -->
            <div>
              <Select
                v-model="selectedValue"
                :options="selectOptions"
                label="University Campus"
                placeholder="Select SFU or other..."
                searchable
              />
              <Textarea
                v-model="textAraVal"
                label="Outreach Statement"
                placeholder="Why do you wish to join?"
                autoResize
              />
            </div>
          </div>

          <!-- Toggles & Selections -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-6 border-t border-neutral-ivory/30 mt-6">
            <!-- Checkbox -->
            <div class="space-y-4">
              <span class="text-[10px] font-bold uppercase tracking-widest text-neutral-muted block">Checkbox Options</span>
              <Checkbox
                v-model="checkboxVal"
                label="Accept Terms & Guidelines"
                description="I agree to follow the student code of conduct."
              />
            </div>

            <!-- Radio -->
            <div>
              <RadioGroup v-model="radioVal" name="gender-group" label="Gender Preference" inline>
                <RadioGroupItem value="male" label="Male" />
                <RadioGroupItem value="female" label="Female" />
              </RadioGroup>
            </div>

            <!-- Switch -->
            <div class="space-y-4">
              <span class="text-[10px] font-bold uppercase tracking-widest text-neutral-muted block">Switch Toggle</span>
              <Switch
                v-model="switchVal"
                label="Mute Notifications"
                description="Blocks notifications to your mobile device."
              />
            </div>
          </div>
        </Card>
      </section>

      <!-- Tab 2: Layout & Navigation Previews -->
      <section v-if="activeTab === 'layouts'" class="space-y-10">
        <!-- Sticky Navbar Simulation -->
        <Card variant="default">
          <template #header>
            <h3 class="font-semibold text-lg">Sticky Navbar Preview</h3>
          </template>
          <Navbar
            brandName="SFU MSA Platform"
            :navItems="navLinks"
            :isAuthenticated="true"
            :user="{ name: 'Abdullah Mohammed', email: 'abdullah@sfu.ca', role: 'admin' }"
            @logout="toast.info('Sign out triggered!')"
          />
        </Card>

        <!-- Sidebar Grid and Collapsible Simulation -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
          <div class="lg:col-span-1 border border-neutral-ivory rounded-2xl overflow-hidden bg-white shadow-soft">
            <Sidebar
              title="Dawah Portal"
              :items="sidebarItems"
              :collapsed="false"
              @collapse="(col) => toast.info(`Sidebar collapse: ${col}`)"
            />
          </div>

          <div class="lg:col-span-3 space-y-6">
            <Card variant="premium" class="h-full flex flex-col justify-between">
              <div>
                <h4 class="font-display font-bold text-xl text-primary mb-3">Layout Sidebar Integration</h4>
                <p class="text-sm text-neutral-muted leading-relaxed">
                  The collapsible sidebar aligns with active route links and expands or contracts its viewport layout presence. Click the arrow button in the header of the sidebar to collapse the width to 80px!
                </p>
              </div>

              <!-- Action buttons to test overlay panels -->
              <div class="flex flex-wrap gap-4 pt-6 border-t border-neutral-ivory/30">
                <Button variant="outline" @click="isDrawerOpen = true">
                  Slide Open Drawer (Right)
                </Button>
                <Button variant="outline" @click="isMobileNavOpen = true">
                  Mobile Menu Overlay
                </Button>
              </div>
            </Card>
          </div>
        </div>

        <!-- Drawer Drawer -->
        <Drawer
          :isOpen="isDrawerOpen"
          title="Academy Settings Drawer"
          @close="isDrawerOpen = false"
        >
          <div class="space-y-6">
            <p class="text-sm text-neutral-muted">
              Sliders appear smoothly from screen sides. Perfect for configuration tabs.
            </p>
            <Input v-model="textVal" label="Drawer Field" placeholder="Type..." />
          </div>
          <template #footer>
            <Button variant="ghost" @click="isDrawerOpen = false">Cancel</Button>
            <Button variant="primary" @click="isDrawerOpen = false">Save Settings</Button>
          </template>
        </Drawer>

        <!-- Mobile Drawer -->
        <MobileNav
          :isOpen="isMobileNavOpen"
          :navItems="navLinks"
          :isAuthenticated="false"
          @close="isMobileNavOpen = false"
        />
      </section>

      <!-- Tab 3: Data Display & Progress Metrics -->
      <section v-if="activeTab === 'data'" class="space-y-10">
        <!-- SVG Charts Wrapper -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <Chart title="LMS User Registrations (Line)" type="line" :data="chartData" />
          <Chart title="Weekly Active Volunteers (Bar)" type="bar" :data="chartData" color="#b02e32" />
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <Chart title="Monthly Outreach (Area)" type="area" :data="chartData" color="#065f46" />
          <Chart title="Role Registrations Distribution (Pie)" type="pie" :data="chartData" />
        </div>

        <!-- Data table wrapper -->
        <Card variant="default">
          <template #header>
            <h3 class="font-semibold text-lg">Registered Volunteers Directory</h3>
          </template>
          <Table
            :columns="tableCols"
            :items="tableItems"
            :currentPage="1"
            :totalPages="3"
            :totalItems="15"
            @sort="(pl) => toast.info(`Sort key: ${pl.key}, order: ${pl.order}`)"
            @page-change="(p) => toast.info(`Requested Page: ${p}`)"
          >
            <!-- Custom Cell Templates -->
            <template #status="{ item }">
              <Badge :variant="item.status === 'Active' ? 'success' : 'outline'" isPulse>
                {{ item.status }}
              </Badge>
            </template>
            <template #role="{ item }">
              <span class="font-semibold text-primary text-xs">{{ item.role }}</span>
            </template>
          </Table>
        </Card>

        <!-- Badges & Progress Indicators -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <!-- Status Badges -->
          <Card variant="flat">
            <h4 class="font-semibold mb-4">Badges & Status Tags</h4>
            <div class="flex flex-wrap gap-2">
              <Badge variant="primary">Maroon</Badge>
              <Badge variant="secondary">Ivory</Badge>
              <Badge variant="success" isPulse>Success</Badge>
              <Badge variant="warning">Warning</Badge>
              <Badge variant="error">Danger</Badge>
              <Badge variant="info">Info</Badge>
              <Badge variant="gold" isShimmer>Gold</Badge>
            </div>
            
            <div class="mt-6 space-y-4">
              <span class="text-[10px] font-bold uppercase tracking-widest text-neutral-muted block">Theological Rank Badge</span>
              <RoleBadge role="alim" />
              <RoleBadge role="mujtahid" />
            </div>
          </Card>

          <!-- Achievement Badge rotating hex -->
          <Card variant="default">
            <h4 class="font-semibold mb-4">Achievement Medal Seals</h4>
            <div class="space-y-4">
              <AchievementBadge
                title="Fiqh Specialist"
                description="Successfully passes all 12 modules of Fiqh."
                :xp="500"
                unlockedAt="May 2026"
              />
              <AchievementBadge
                title="Hadith Master"
                description="Memorize and explain the complete 40 Hadith of Nawawi."
                :xp="1500"
                isLocked
              />
            </div>
          </Card>

          <!-- Progress Bars (Linear / Circular / Timeline) -->
          <Card variant="default">
            <h4 class="font-semibold mb-4">Progress Gauges</h4>
            <div class="space-y-5">
              <LmsProgressBar :value="72" showLabel />
              <LmsProgressBar :value="40" color="bg-secondary" showLabel />
              
              <div class="flex items-center justify-around py-3 border-t border-b border-neutral-ivory/40">
                <LmsCircularProgress :value="88" />
                <LmsCircularProgress :value="32" color="text-secondary" />
              </div>

              <div>
                <span class="text-[10px] font-bold uppercase tracking-widest text-neutral-muted block mb-3">Pathway timeline</span>
                <LmsMilestoneTracker :milestones="milestonesList" />
              </div>
            </div>
          </Card>
        </div>
      </section>

      <!-- Tab 4: Feedback Alerts & Loaders -->
      <section v-if="activeTab === 'feedback'" class="space-y-10">
        <!-- Alerts -->
        <Card variant="premium">
          <template #header>
            <h3 class="font-semibold text-lg">Feedback & Inline Alerts</h3>
          </template>
          <div class="space-y-4">
            <Alert type="success" title="Evaluation Submitted">
              Your assignment files were submitted. A grader will assign grades shortly.
            </Alert>
            <Alert type="warning" title="Outreach Action Required" closable>
              Please register your volunteer availability before Friday event schedules.
            </Alert>
            <Alert type="error" title="Sanctum Authorization Error">
              Access denied. Credentials do not match volunteer database registers.
            </Alert>
            <Alert type="info" title="System Maintenance Notice">
              Platform migration scheduled on Sunday 2:00 AM.
            </Alert>
          </div>
        </Card>

        <!-- Skeletons & Loaders -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <!-- Skeletons -->
          <Card variant="default">
            <h4 class="font-semibold mb-4">Skeleton Loaders</h4>
            <div class="space-y-6">
              <Skeleton type="text" />
              <div class="border-t border-neutral-ivory/50 pt-4 flex items-center gap-3">
                <Skeleton type="avatar" />
                <div class="h-4 bg-neutral-ivory/70 rounded w-1/3 animate-pulse"></div>
              </div>
              <Skeleton type="table-row" />
              <Skeleton type="card" />
            </div>
          </Card>

          <!-- Interactive Loaders -->
          <Card variant="default" class="flex flex-col justify-between">
            <div>
              <h4 class="font-semibold mb-4">Interactions & Loading spinners</h4>
              <p class="text-sm text-neutral-muted leading-relaxed mb-6">
                Test the global full screen loader block by triggering the button below. It will show a blocking blur screen for 2.5 seconds and trigger a success toast when complete!
              </p>
              
              <div class="flex items-center gap-6">
                <Loader type="spinner" label="Loading curriculum..." />
                <Loader type="inline" label="Authorizing..." />
              </div>
            </div>

            <div class="flex flex-wrap gap-4 pt-6 border-t border-neutral-ivory/30 mt-6">
              <Button @click="triggerFullscreenLoader">
                Launch Full Screen Loader
              </Button>
              <Button variant="success" @click="toast.success('Course registered!')">
                Trigger Success Toast
              </Button>
              <Button variant="destructive" @click="toast.error('An error has occurred!')">
                Trigger Error Toast
              </Button>
            </div>
          </Card>
        </div>
      </section>
    </div>

    <!-- Sticky Full Screen Loader Overlay -->
    <Loader
      v-if="isFullscreenLoaderOpen"
      type="fullscreen"
      label="Synchronizing with Laravel API Sanctum..."
    />
    
    <!-- Dialog modal popup -->
    <Dialog
      :isOpen="isDialogOpen"
      title="Create New Dawah Module"
      @close="isDialogOpen = false"
    >
      <div class="space-y-4">
        <p>Complete the form details below to add a new lesson curriculum path.</p>
        <Input v-model="textVal" label="Module Title" placeholder="E.g. Fiqh and Logic" required />
        <Textarea v-model="textAraVal" label="Syllabus Description" placeholder="Explain targets..." />
      </div>
      <template #footer>
        <Button variant="ghost" @click="isDialogOpen = false">Cancel</Button>
        <Button variant="primary" @click="isDialogOpen = false">Save Module</Button>
      </template>
    </Dialog>
    
    <div class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 mt-12 flex justify-center">
      <Button variant="primary" size="lg" @click="isDialogOpen = true">
        Open Dialog Form Modal
      </Button>
    </div>

    <!-- Site footer preview -->
    <div class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 mt-16 border-t border-neutral-ivory/60 pt-16">
      <h3 class="text-xs font-bold uppercase tracking-widest text-neutral-muted mb-6 text-center">Footer Preview</h3>
      <Footer />
    </div>

  </div>
</template>
