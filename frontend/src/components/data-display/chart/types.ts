export interface ChartDataPoint {
  label: string;
  value: number;
}

export interface ChartProps {
  data: ChartDataPoint[];
  title?: string;
  height?: number;
  color?: string;
}
