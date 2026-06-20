# Radio Components

## Purpose
A collection of options where only one option can be selected. The parent `RadioGroup` coordinates value selections and options context, while `RadioGroupItem` renders individual choices.

## Props

### RadioGroup Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `modelValue` | `string \| number \| boolean` | *Required* | Active group value |
| `name` | `string` | *Required* | Group input element name binding |
| `label` | `string` | `undefined` | Display label above options list |
| `description` | `string` | `undefined` | Secondary help text |
| `error` | `string` | `undefined` | Error warning label |
| `disabled` | `boolean` | `false` | Disables whole group selection |
| `required` | `boolean` | `false` | Displays asterisk indicator |
| `inline` | `boolean` | `false` | Render list horizontally |

### RadioGroupItem Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `value` | `string \| number \| boolean` | *Required* | Unique value matching group selection |
| `label` | `string` | *Required* | Option label description |
| `description` | `string` | `undefined` | Secondary helper label |
| `disabled` | `boolean` | `false` | Disables single item interaction |

## Events

### RadioGroup Events
| Event | Payload | Description |
| :--- | :--- | :--- |
| `update:modelValue` | `string \| number \| boolean` | Emitted on option changes |
| `change` | `string \| number \| boolean` | Emitted on option changes |

## Examples

### Horizontal Group
```vue
<RadioGroup v-model="gender" name="gender" label="Gender" inline>
  <RadioGroupItem value="male" label="Male" />
  <RadioGroupItem value="female" label="Female" />
</RadioGroup>
```
