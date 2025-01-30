export class ShowCount {
  #count;

  constructor(count) {
    this.#count = count;
  }

  get count() {
    return this.#count;
  }

  increment() {
    this.#count++;
  }

  decrement() {
    this.#count--;
  }
}
