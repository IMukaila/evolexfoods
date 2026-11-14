import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { ApiService } from '../../core/services/api.service';

export interface Animal {
  id: number;
  animal_id: string;
  tag_id?: string;
  tag_number?: string;
  species: string;
  breed?: string;
  sex: string;
  dob?: string;
  age?: number | string;
  location?: { id?: number; name: string; code?: string };
  location_id?: number;
  weight?: number;
  cost?: number;
  source?: string;
  health_status: string;
  status: string;
  current_status?: string;
  pregnancy_status?: string;
  breeding_status?: string;
  notes?: string;
}

export interface Filters {
  search: string;
  species: string;
  sex: string;
  location: string;
  health_status: string;
  status: string;
}

@Component({
  selector: 'app-livestock',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './livestock.component.html',
  styleUrls: ['./livestock.component.scss']
})
export class LivestockComponent implements OnInit {
  summary: any = { by_location: [], by_species: [], by_age: {} };
  births: any = { count: 0 };
  deaths: any = { count: 0 };
  growth: any = { herd_growth: 0, herd_growth_rate: 0 };
  mortality: any = { mortality_rate: 0 };
  animals: Animal[] = [];
  
  // Pagination
  currentPage = 1;
  pageSize = 10;
  totalRecords = 0;
  totalPages = 1;
  fromRecord = 0;
  toRecord = 0;
  paginatedAnimals: Animal[] = [];
  Math = Math; // Expose Math to template
  
  filters: Filters = {
    search: '',
    species: '',
    sex: '',
    location: '',
    health_status: '',
    status: ''
  };

  showFilters = false;
  selectedAnimals = new Set<number>();
  
  // Filter options
  speciesOptions = ['Goat', 'Sheep', 'Cattle', 'Chicken', 'Pig'];
  sexOptions = ['Male', 'Female'];
  locationOptions: string[] = [];
  healthStatusOptions = ['Healthy', 'Sick', 'Quarantine', 'Under Treatment'];
  statusOptions = ['Active', 'Alive', 'Dead', 'Sold', 'Transferred'];
  locationsList: { id: number; name: string; code?: string }[] = [];

  // Modals
  showModal = false;
  isEditing = false;
  modalAnimal: Partial<Animal> = {};
  showViewModal = false;
  viewingAnimal: Animal | null = null;

  // Birth lifecycle automation (§96): litter form, auto-ID preview, result.
  showBirthModal = false;
  birthSaving = false;
  birthError = '';
  birthResult: { animals: Animal[]; litter_size: number; pregnancy_closed: boolean } | null = null;
  birthForm: {
    mother_animal_id: string;
    father_animal_id: string;
    birth_date: string;
    location_id: number | null;
    notes: string;
  } = { mother_animal_id: '', father_animal_id: '', birth_date: '', location_id: null, notes: '' };
  birthKids: { sex: string; birth_weight: number | null }[] = [{ sex: 'female', birth_weight: null }];
  herdAnimals: { id: number; animal_id: string; species?: string; sex?: string; status?: string }[] = [];
  nextIdPreview = '';
  nextIdSpecies = '';

  loadingNextId = false;
  modalSaving = false;
  modalError = '';

  constructor(private api: ApiService, private router: Router) {}

  ngOnInit(): void {
    this.load();
    this.loadLocations();
    this.loadHerdAnimals();
  }

  /* ---------------- Birth lifecycle automation (§96) ---------------- */

  loadHerdAnimals(): void {
    this.api.get<any>('/lookups').subscribe((d) => {
      this.herdAnimals = Array.isArray(d?.animals) ? d.animals : [];
    });
  }

  get dams(): { id: number; animal_id: string; species?: string; sex?: string; status?: string }[] {
    return this.herdAnimals.filter((a) => (a.sex || '').toLowerCase() === 'female' && (a.status || 'alive') !== 'dead');
  }

  get sires(): { id: number; animal_id: string; species?: string; sex?: string; status?: string }[] {
    return this.herdAnimals.filter((a) => (a.sex || '').toLowerCase() === 'male' && (a.status || 'alive') !== 'dead');
  }

  openBirth(): void {
    this.birthForm = {
      mother_animal_id: '',
      father_animal_id: '',
      birth_date: new Date().toISOString().slice(0, 10),
      location_id: this.locationsList[0]?.id ?? null,
      notes: ''
    };
    this.birthKids = [{ sex: 'female', birth_weight: null }];
    this.birthError = '';
    this.birthResult = null;
    this.nextIdPreview = '';
    this.showBirthModal = true;
  }

  closeBirth(): void {
    this.showBirthModal = false;
  }

  onDamChange(): void {
    const dam = this.herdAnimals.find((a) => a.animal_id === this.birthForm.mother_animal_id);
    if (dam?.species) {
      this.previewNextId(dam.species);
    }
  }

  onSpeciesChange(): void {
    if (!this.isEditing && this.modalAnimal.species) {
      this.previewNextId(this.modalAnimal.species);
    }
  }

  /** Preview the next auto-generated EV-… ID without consuming it and sync with modalAnimal. */
  previewNextId(species?: string): void {
    if (!species) return;
    this.nextIdSpecies = species;
    this.loadingNextId = true;
    this.api.get<any>('/livestock/next-id', { species }).subscribe({
      next: (d) => {
        this.loadingNextId = false;
        if (this.nextIdSpecies === species) {
          const nextId = d?.next_animal_id || this.getFallbackId(species);
          this.nextIdPreview = nextId;
          if (!this.isEditing) {
            this.modalAnimal.animal_id = nextId;
          }
        }
      },
      error: () => {
        this.loadingNextId = false;
        const fallback = this.getFallbackId(species);
        this.nextIdPreview = fallback;
        if (!this.isEditing) {
          this.modalAnimal.animal_id = fallback;
        }
      }
    });
  }

  getFallbackId(species: string): string {
    const s = (species || '').toLowerCase();
    if (s.includes('sheep') || s.includes('sh')) return 'EV-SH-000001';
    if (s.includes('catt') || s.includes('cow') || s.includes('ct')) return 'EV-CT-000001';
    return 'EV-GT-000001';
  }

  addKid(): void {
    this.birthKids = [...this.birthKids, { sex: 'female', birth_weight: null }];
  }

  removeKid(index: number): void {
    this.birthKids = this.birthKids.filter((_, i) => i !== index);
  }

  submitBirth(): void {
    if (!this.birthForm.mother_animal_id) {
      this.birthError = 'Select the dam — her ID is validated before any kid is registered.';
      return;
    }
    if (this.birthKids.length === 0) {
      this.birthError = 'Add at least one kid to register.';
      return;
    }
    this.birthSaving = true;
    this.birthError = '';
    const dam = this.herdAnimals.find((a) => a.animal_id === this.birthForm.mother_animal_id);
    this.api
      .post<any>('/livestock/events/birth', {
        mother_animal_id: this.birthForm.mother_animal_id,
        father_animal_id: this.birthForm.father_animal_id || null,
        species: dam?.species || null,
        birth_date: this.birthForm.birth_date || null,
        location_id: this.birthForm.location_id,
        notes: this.birthForm.notes || null,
        kids: this.birthKids.map((kid) => ({ sex: kid.sex, birth_weight: kid.birth_weight }))
      })
      .subscribe({
        next: (result) => {
          this.birthSaving = false;
          this.birthResult = {
            animals: result.animals || (result.animal ? [result.animal] : []),
            litter_size: result.litter_size || 1,
            pregnancy_closed: !!result.pregnancy_closed
          };
          this.load();
          this.loadHerdAnimals();
        },
        error: (err) => {
          this.birthSaving = false;
          const errors = err.error?.errors;
          this.birthError = errors ? Object.values(errors).flat().join(' ') : err.error?.message || 'The birth could not be registered.';
        }
      });
  }

  load(): void {
    this.api.get<any>('/livestock/summary').subscribe((d) => {
      this.summary = d;
      if (d.by_location && d.by_location.length > 0) {
        this.locationOptions = d.by_location.map((l: any) => l.location);
      }
    });
    this.api.get<any>('/livestock/births').subscribe((d) => this.births = d || { count: 0 });
    this.api.get<any>('/livestock/deaths').subscribe((d) => this.deaths = d || { count: 0 });
    this.api.get<any>('/livestock/growth').subscribe((d) => this.growth = d || { herd_growth: 0, herd_growth_rate: 0 });
    this.api.get<any>('/livestock/mortality-rate').subscribe((d) => this.mortality = d || { mortality_rate: 0 });
    this.search(1);
  }

  loadLocations(): void {
    this.api.get<any>('/locations').subscribe((locs) => {
      if (Array.isArray(locs)) {
        this.locationsList = locs;
        if (this.locationOptions.length === 0) {
          this.locationOptions = locs.map((l: any) => l.name);
        }
      }
    });
  }

  search(page: number = 1): void {
    this.currentPage = page;
    const params: any = {
      page: this.currentPage,
      per_page: this.pageSize
    };
    if (this.filters.search) params.q = this.filters.search;
    if (this.filters.species) params.species = this.filters.species;
    if (this.filters.sex) params.sex = this.filters.sex;
    if (this.filters.location) params.location = this.filters.location;
    if (this.filters.health_status) params.health_status = this.filters.health_status;
    if (this.filters.status) params.status = this.filters.status;

    this.api.get<any>('/livestock', params).subscribe((d) => {
      if (d && d.data) {
        this.animals = d.data;
        this.paginatedAnimals = d.data;
        this.totalRecords = d.total ?? d.data.length;
        this.currentPage = d.current_page ?? page;
        this.totalPages = d.last_page ?? Math.max(1, Math.ceil(this.totalRecords / this.pageSize));
        this.fromRecord = d.from ?? (this.totalRecords > 0 ? (this.currentPage - 1) * this.pageSize + 1 : 0);
        this.toRecord = d.to ?? Math.min(this.currentPage * this.pageSize, this.totalRecords);
      } else if (Array.isArray(d)) {
        this.animals = d;
        this.totalRecords = d.length;
        this.totalPages = Math.max(1, Math.ceil(this.totalRecords / this.pageSize));
        this.fromRecord = this.totalRecords > 0 ? (this.currentPage - 1) * this.pageSize + 1 : 0;
        this.toRecord = Math.min(this.currentPage * this.pageSize, this.totalRecords);
        this.paginatedAnimals = d.slice(this.fromRecord - 1, this.toRecord);
      } else {
        this.animals = [];
        this.paginatedAnimals = [];
        this.totalRecords = 0;
        this.totalPages = 1;
        this.fromRecord = 0;
        this.toRecord = 0;
      }
    });
  }

  onPageSizeChange(): void {
    this.search(1);
  }

  nextPage(): void {
    if (this.currentPage < this.totalPages) {
      this.search(this.currentPage + 1);
    }
  }

  prevPage(): void {
    if (this.currentPage > 1) {
      this.search(this.currentPage - 1);
    }
  }

  goToPage(page: number): void {
    if (page >= 1 && page <= this.totalPages && page !== this.currentPage) {
      this.search(page);
    }
  }

  getPageNumbers(): (number | string)[] {
    const pages: (number | string)[] = [];
    const total = this.totalPages;
    const current = this.currentPage;

    if (total <= 7) {
      for (let i = 1; i <= total; i++) pages.push(i);
    } else {
      pages.push(1);
      if (current > 3) pages.push('...');
      
      const start = Math.max(2, current - 1);
      const end = Math.min(total - 1, current + 1);
      
      for (let i = start; i <= end; i++) {
        pages.push(i);
      }
      
      if (current < total - 2) pages.push('...');
      pages.push(total);
    }
    return pages;
  }

  clearFilters(): void {
    this.filters = {
      search: '',
      species: '',
      sex: '',
      location: '',
      health_status: '',
      status: ''
    };
    this.search(1);
  }

  toggleFilters(): void {
    this.showFilters = !this.showFilters;
  }

  hasActiveFilters(): boolean {
    return !!(this.filters.species || this.filters.sex || this.filters.location || 
              this.filters.health_status || this.filters.status);
  }

  toggleSelectAll(event: any): void {
    if (event.target.checked) {
      this.paginatedAnimals.forEach(a => this.selectedAnimals.add(a.id));
    } else {
      this.paginatedAnimals.forEach(a => this.selectedAnimals.delete(a.id));
    }
  }

  isAllSelected(): boolean {
    return this.paginatedAnimals.length > 0 && 
           this.paginatedAnimals.every(a => this.selectedAnimals.has(a.id));
  }

  toggleSelect(id: number): void {
    if (this.selectedAnimals.has(id)) {
      this.selectedAnimals.delete(id);
    } else {
      this.selectedAnimals.add(id);
    }
  }

  isSelected(id: number): boolean {
    return this.selectedAnimals.has(id);
  }

  viewAnimal(animal: Animal): void {
    this.viewingAnimal = animal;
    this.showViewModal = true;
  }

  closeViewModal(): void {
    this.showViewModal = false;
    this.viewingAnimal = null;
  }

  addNew(): void {
    this.isEditing = false;
    this.modalError = '';
    this.modalSaving = false;
    this.modalAnimal = {
      species: 'goat',
      animal_id: 'EV-GT-000001',
      sex: 'female',
      health_status: 'healthy',
      status: 'alive',
      current_status: 'active',
      location_id: this.locationsList[0]?.id
    };
    this.showModal = true;
    this.previewNextId('goat');
  }

  editAnimal(animal: Animal): void {
    this.isEditing = true;
    this.modalError = '';
    this.modalSaving = false;
    this.modalAnimal = {
      id: animal.id,
      animal_id: animal.animal_id,
      tag_id: animal.tag_id || animal.tag_number,
      species: (animal.species || '').toLowerCase(),
      breed: animal.breed,
      sex: (animal.sex || '').toLowerCase(),
      dob: animal.dob ? animal.dob.split('T')[0] : '',
      location_id: animal.location?.id || animal.location_id,
      weight: animal.weight,
      health_status: (animal.health_status || 'healthy').toLowerCase(),
      status: (animal.status || 'alive').toLowerCase(),
      notes: animal.notes
    };
    this.showModal = true;
  }

  closeModal(): void {
    this.showModal = false;
    this.modalAnimal = {};
    this.modalError = '';
    this.modalSaving = false;
  }

  saveAnimal(): void {
    if (!this.modalAnimal.species) {
      this.modalError = 'Species is required.';
      return;
    }
    if (!this.modalAnimal.animal_id) {
      this.modalError = 'Animal ID is required.';
      return;
    }

    this.modalSaving = true;
    this.modalError = '';

    if (this.isEditing && this.modalAnimal.id) {
      this.api.put(`/livestock/${this.modalAnimal.id}`, this.modalAnimal).subscribe({
        next: () => {
          this.modalSaving = false;
          this.closeModal();
          this.load();
        },
        error: (err) => {
          this.modalSaving = false;
          this.modalError = err.error?.message || (err.error?.errors ? Object.values(err.error.errors).flat().join(' ') : 'Error updating animal');
        }
      });
    } else {
      this.api.post('/livestock', this.modalAnimal).subscribe({
        next: () => {
          this.modalSaving = false;
          this.closeModal();
          this.load();
          this.loadHerdAnimals();
        },
        error: (err) => {
          this.modalSaving = false;
          this.modalError = err.error?.message || (err.error?.errors ? Object.values(err.error.errors).flat().join(' ') : 'Error adding animal');
        }
      });
    }
  }

  deleteAnimal(animal: Animal): void {
    if (confirm(`Are you sure you want to delete ${animal.animal_id}?`)) {
      this.api.delete(`/livestock/${animal.id}`).subscribe({
        next: () => {
          this.selectedAnimals.delete(animal.id);
          this.load();
        },
        error: (err) => alert(err.error?.message || 'Error deleting animal')
      });
    }
  }

  exportData(): void {
    const exportList = this.selectedAnimals.size > 0 
      ? this.animals.filter(a => this.selectedAnimals.has(a.id))
      : this.animals;

    if (!exportList || exportList.length === 0) {
      alert('No animals to export');
      return;
    }

    const headers = ['Animal ID', 'Tag ID', 'Species', 'Breed', 'Sex', 'Location', 'Health', 'Status', 'Weight', 'DOB'];
    const rows = exportList.map(a => [
      `"${a.animal_id || ''}"`,
      `"${a.tag_id || a.tag_number || ''}"`,
      `"${a.species || ''}"`,
      `"${a.breed || ''}"`,
      `"${a.sex || ''}"`,
      `"${a.location?.name || ''}"`,
      `"${a.health_status || ''}"`,
      `"${a.status || a.current_status || ''}"`,
      `"${a.weight || ''}"`,
      `"${a.dob || ''}"`
    ]);

    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `livestock_export_${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }

  bulkAction(action: string): void {
    if (this.selectedAnimals.size === 0) {
      alert('Please select at least one animal');
      return;
    }

    if (action === 'delete') {
      if (confirm(`Delete ${this.selectedAnimals.size} selected animals?`)) {
        const ids = Array.from(this.selectedAnimals);
        let completed = 0;
        ids.forEach(id => {
          this.api.delete(`/livestock/${id}`).subscribe({
            next: () => {
              completed++;
              if (completed === ids.length) {
                this.selectedAnimals.clear();
                this.load();
              }
            },
            error: () => {
              completed++;
              if (completed === ids.length) {
                this.selectedAnimals.clear();
                this.load();
              }
            }
          });
        });
      }
    }
  }

  calculateAge(dob?: string): string {
    if (!dob) return 'N/A';
    const birth = new Date(dob);
    const now = new Date();
    const diffMonths = (now.getFullYear() - birth.getFullYear()) * 12 + (now.getMonth() - birth.getMonth());
    if (isNaN(diffMonths) || diffMonths < 0) return 'N/A';
    if (diffMonths < 12) return `${diffMonths}m`;
    const years = Math.floor(diffMonths / 12);
    const remMonths = diffMonths % 12;
    return remMonths > 0 ? `${years}y ${remMonths}m` : `${years}y`;
  }

  getHealthBadgeClass(status?: string): string {
    if (!status) return 'healthy';
    return status.toLowerCase().replace(/\s+/g, '-');
  }

  getStatusBadgeClass(status?: string): string {
    if (!status) return 'active';
    return status.toLowerCase().replace(/\s+/g, '-');
  }
}
