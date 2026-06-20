<script setup lang="ts">
useAttrs: false;
import { ref, onMounted, watch } from 'vue';
import { 
  Bold, 
  Italic, 
  Heading2, 
  Heading3, 
  List, 
  ListOrdered, 
  Link as LinkIcon, 
  Quote, 
  AlertCircle 
} from 'lucide-vue-next';

const props = defineProps<{
  modelValue: string;
  label?: string;
  placeholder?: string;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void;
}>();

const editorRef = ref<HTMLDivElement | null>(null);
const isFocused = ref(false);

// Format commands using standard contenteditable API
const format = (command: string, value: string = '') => {
  document.execCommand(command, false, value);
  updateValue();
};

const insertLink = () => {
  const url = prompt('Enter the link URL:');
  if (url) {
    format('createLink', url);
  }
};

const insertCallout = () => {
  // Insert a styled callout block
  const selection = window.getSelection();
  if (selection && selection.rangeCount > 0) {
    const range = selection.getRangeAt(0);
    const callout = document.createElement('div');
    callout.className = 'my-4 p-4 bg-primary/5 border border-primary/20 rounded-2xl text-neutral-black text-sm flex gap-3 items-start';
    callout.innerHTML = `
      <span class="text-primary mt-0.5 font-bold">📢</span>
      <div class="flex-grow font-sans italic">Callout text...</div>
    `;
    range.insertNode(callout);
    updateValue();
  }
};

const updateValue = () => {
  if (editorRef.value) {
    emit('update:modelValue', editorRef.value.innerHTML);
  }
};

onMounted(() => {
  if (editorRef.value && props.modelValue) {
    editorRef.value.innerHTML = props.modelValue;
  }
});

// Watch external value changes, but only sync if different to avoid cursor jump
watch(() => props.modelValue, (newVal) => {
  if (editorRef.value && editorRef.value.innerHTML !== newVal) {
    editorRef.value.innerHTML = newVal || '';
  }
});
</script>

<template>
  <div class="space-y-2 flex flex-col text-neutral-black">
    <label v-if="label" class="text-xs font-black uppercase tracking-widest text-primary/70">
      {{ label }}
    </label>
    
    <div 
      :class="[
        'rounded-3xl border transition-all duration-300 overflow-hidden bg-white shadow-soft flex flex-col',
        isFocused ? 'border-primary ring-4 ring-primary/5' : 'border-neutral-gray/20'
      ]"
    >
      <!-- Toolbar -->
      <div class="flex flex-wrap items-center gap-1.5 p-3 border-b border-neutral-gray/10 bg-neutral-background/50 backdrop-blur-sm">
        <button
          type="button"
          @click="format('bold')"
          class="p-2.5 rounded-xl hover:bg-primary/5 hover:text-primary transition-colors cursor-pointer text-neutral-black/60"
          title="Bold"
        >
          <Bold :size="16" />
        </button>
        <button
          type="button"
          @click="format('italic')"
          class="p-2.5 rounded-xl hover:bg-primary/5 hover:text-primary transition-colors cursor-pointer text-neutral-black/60"
          title="Italic"
        >
          <Italic :size="16" />
        </button>
        
        <div class="h-6 w-[1px] bg-neutral-gray/20 mx-1" />

        <button
          type="button"
          @click="format('formatBlock', 'h2')"
          class="p-2.5 rounded-xl hover:bg-primary/5 hover:text-primary transition-colors cursor-pointer text-neutral-black/60"
          title="Heading 2"
        >
          <Heading2 :size="16" />
        </button>
        <button
          type="button"
          @click="format('formatBlock', 'h3')"
          class="p-2.5 rounded-xl hover:bg-primary/5 hover:text-primary transition-colors cursor-pointer text-neutral-black/60"
          title="Heading 3"
        >
          <Heading3 :size="16" />
        </button>
        
        <div class="h-6 w-[1px] bg-neutral-gray/20 mx-1" />

        <button
          type="button"
          @click="format('insertUnorderedList')"
          class="p-2.5 rounded-xl hover:bg-primary/5 hover:text-primary transition-colors cursor-pointer text-neutral-black/60"
          title="Bullet List"
        >
          <List :size="16" />
        </button>
        <button
          type="button"
          @click="format('insertOrderedList')"
          class="p-2.5 rounded-xl hover:bg-primary/5 hover:text-primary transition-colors cursor-pointer text-neutral-black/60"
          title="Numbered List"
        >
          <ListOrdered :size="16" />
        </button>
        
        <div class="h-6 w-[1px] bg-neutral-gray/20 mx-1" />

        <button
          type="button"
          @click="insertLink"
          class="p-2.5 rounded-xl hover:bg-primary/5 hover:text-primary transition-colors cursor-pointer text-neutral-black/60"
          title="Insert Link"
        >
          <LinkIcon :size="16" />
        </button>
        <button
          type="button"
          @click="format('formatBlock', 'blockquote')"
          class="p-2.5 rounded-xl hover:bg-primary/5 hover:text-primary transition-colors cursor-pointer text-neutral-black/60"
          title="Quote Block"
        >
          <Quote :size="16" />
        </button>
        <button
          type="button"
          @click="insertCallout"
          class="p-2.5 rounded-xl hover:bg-primary/5 hover:text-primary transition-colors cursor-pointer text-neutral-black/60"
          title="Callout Block"
        >
          <AlertCircle :size="16" />
        </button>
      </div>

      <!-- Editable Area -->
      <div 
        ref="editorRef"
        contenteditable="true"
        @input="updateValue"
        @focus="isFocused = true"
        @blur="isFocused = false"
        :placeholder="placeholder || 'Write beautiful rich content here...'"
        class="p-6 min-h-[220px] focus:outline-none overflow-y-auto text-sm leading-relaxed prose prose-sm max-w-none prose-primary font-sans"
      ></div>
    </div>
  </div>
</template>

<style scoped>
/* Placeholder trick for contenteditable */
[contenteditable="true"]:empty:before {
  content: attr(placeholder);
  color: #a3a3a3;
  pointer-events: none;
  display: block; /* For Firefox */
}
</style>
