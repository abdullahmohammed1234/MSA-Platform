export interface TableColumn {
  key: string;
  label: string;
  sortable?: boolean;
}

export interface TableProps {
  columns: TableColumn[];
  items: any[];
  isLoading?: boolean;
  emptyText?: string;
  currentPage?: number;
  totalPages?: number;
  totalItems?: number;
  itemsPerPage?: number;
}
